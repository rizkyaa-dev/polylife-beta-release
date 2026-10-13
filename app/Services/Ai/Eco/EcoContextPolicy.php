<?php

namespace App\Services\Ai\Eco;

final readonly class EcoContextPolicy
{
    public const VERSION = 1;

    public function __construct(
        public int $tokenBudget,
        public int $recentTurns,
        public int $memoryTokenBudget,
        public int $retrievalMessageLimit
    ) {}

    public static function configured(): self
    {
        return new self(
            max(2000, min(24000, (int) config('ai_eco.context_token_budget', 6000))),
            max(1, min(8, (int) config('ai_eco.recent_turns', 2))),
            max(200, min(4000, (int) config('ai_eco.memory_token_budget', 1200))),
            max(240, min(2000, (int) config('ai_eco.retrieval_message_limit', 1000)))
        );
    }

    public static function fromArray(mixed $value): ?self
    {
        if (! is_array($value) || ($value['version'] ?? null) !== self::VERSION) {
            return null;
        }
        foreach (['token_budget', 'recent_turns', 'memory_token_budget', 'retrieval_message_limit'] as $key) {
            if (! is_int($value[$key] ?? null)) {
                return null;
            }
        }
        if ($value['token_budget'] < 2000 || $value['token_budget'] > 24000
            || $value['recent_turns'] < 1 || $value['recent_turns'] > 8
            || $value['memory_token_budget'] < 200 || $value['memory_token_budget'] > 4000
            || $value['retrieval_message_limit'] < 240 || $value['retrieval_message_limit'] > 2000) {
            return null;
        }

        return new self($value['token_budget'], $value['recent_turns'], $value['memory_token_budget'], $value['retrieval_message_limit']);
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'token_budget' => $this->tokenBudget,
            'recent_turns' => $this->recentTurns,
            'memory_token_budget' => $this->memoryTokenBudget,
            'retrieval_message_limit' => $this->retrievalMessageLimit,
        ];
    }
}
