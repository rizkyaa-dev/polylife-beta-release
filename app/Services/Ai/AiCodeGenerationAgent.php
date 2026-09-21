<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Design\AiDesignIntentResolver;
use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiCodingRoute;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Exceptions\AiProviderException;

final class AiCodeGenerationAgent
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly AiCodingPromptBuilder $promptBuilder,
        private readonly AiDesignIntentResolver $designs
    ) {}

    public function generate(
        string $prompt,
        AiCodingBrief $brief,
        AiCodingRoute $route,
        LlmRequestOptions $options,
        ?string $sourceArtifact = null,
        ?array $scienceContract = null
    ): LlmResponse {
        if ($route->language !== $brief->language) {
            throw AiProviderException::invalidCodingResponse();
        }
        $designPlan = $this->designs->resolve($brief, $sourceArtifact !== null);
        if ($designPlan !== null) {
            // The coder needs decisions/tokens, not duplicated intent or an arithmetic audit.
            if ($brief->designIntent !== null) {
                unset($designPlan['intent']);
            }
            if ($designPlan['tokens'] !== null) {
                unset($designPlan['tokens']['verification']);
                if (! $designPlan['tokens']['palette_selected']) {
                    unset($designPlan['tokens']['colors']);
                }
            }
        }
        $payload = json_encode([
            'coding_brief' => $brief->toArray(),
            'original_request' => $prompt,
            'source_artifact' => $sourceArtifact,
            'design_plan' => $designPlan,
            'science_contract' => $scienceContract,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $systemInstruction = $this->promptBuilder->build($brief, $route, $sourceArtifact !== null, $scienceContract !== null);

        $response = $this->llmClient->chat(
            [new LlmMessage(role: 'user', content: "<delegated_coding_request>\n{$payload}\n</delegated_coding_request>")],
            [],
            $systemInstruction,
            $options
        );

        if ($response->isTruncated()) {
            throw AiProviderException::truncated();
        }

        return $response;
    }
}
