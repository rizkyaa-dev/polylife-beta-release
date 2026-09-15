<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\DTOs\LlmRequestOptions;

class DeepSeekLlmClient extends OpenAiLlmClient
{
    public function __construct(
        ?string $apiKey = null,
        string $model = 'deepseek-flash',
        string $baseUrl = 'https://api.deepseek.com',
        int $timeoutSeconds = 30
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model,
            baseUrl: $baseUrl,
            timeoutSeconds: $timeoutSeconds
        );
    }

    protected function providerName(): string
    {
        return 'DeepSeek';
    }

    /** @param array<string, mixed> $payload */
    protected function preparePayload(array $payload, LlmRequestOptions $options, bool $hasTools): array
    {
        $effort = $options->thinkingEffort;
        $payload['thinking'] = ['type' => $effort->isEnabled() ? 'enabled' : 'disabled'];
        $payload['reasoning_effort'] = $effort->deepSeekValue();

        if ($hasTools && $effort->isEnabled()) {
            unset($payload['tool_choice']);
        }

        return $payload;
    }

    protected function shouldIncludeReasoningContent(bool $hasTools, LlmRequestOptions $options): bool
    {
        return $hasTools;
    }
}
