<?php

namespace App\Services\Ai\Eco;

use App\Services\Ai\AiCodingDelegation;
use App\Services\Ai\AiToolSelectionService;
use App\Services\Ai\Science\AiScienceDelegation;

final class EcoToolSelector
{
    public const DISCOVER = 'discover_workspace_tools';

    public const RECALL = 'recall_conversation';

    public function __construct(
        private readonly AiToolSelectionService $workspace,
        private readonly AiCodingDelegation $coding,
        private readonly AiScienceDelegation $science,
        private readonly EcoContextRelevance $relevance
    ) {}

    public function declarations(string $prompt, array $history): array
    {
        $domains = $this->workspace->matchingDomains($prompt, $this->relevance->isFollowUp($prompt) ? $history : []);

        return [...$this->workspace->forDomains($domains), ...$this->baseDeclarations()];
    }

    public function discover(array $domains): array
    {
        return $this->workspace->forDomains($domains);
    }

    public function domainNames(): array
    {
        return $this->workspace->domainNames();
    }

    public function baseDeclarations(): array
    {
        return [$this->coding->declaration(), $this->science->declaration(), [
            'name' => self::DISCOVER,
            'description' => 'Make the PolyLife tools needed for this request available when they are not yet exposed. This does not read or modify workspace data.',
            'parameters' => [
                'type' => 'object',
                'properties' => ['domains' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $this->workspace->domainNames()], 'minItems' => 1, 'maxItems' => 9]],
                'required' => ['domains'],
            ],
        ], [
            'name' => self::RECALL,
            'description' => 'Retrieve original conversation evidence on the current branch. Use before relying on shortened quotes, old constraints, artifacts or ambiguous references. Does not verify current workspace state.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'maxLength' => 500],
                    'message_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 4],
                ],
            ],
        ]];
    }

    public function merge(array $current, array $additional): array
    {
        $byName = [];
        foreach ([...$current, ...$additional] as $tool) {
            $byName[$tool['name']] = $tool;
        }

        return array_values($byName);
    }
}
