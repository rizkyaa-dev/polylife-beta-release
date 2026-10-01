<?php

namespace App\Services\Ai\DTOs;

final class LlmTokenUsage
{
    public readonly int $totalTokens;

    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        int $totalTokens = 0,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $cachedTokens = null,
        public readonly bool $isComplete = true
    ) {
        // Normalize each call before aggregation; a missing total on a later call
        // must not disappear once an earlier call supplied a positive total.
        $this->totalTokens = $totalTokens > 0 ? $totalTokens : $promptTokens + $completionTokens;
    }

    public function accumulate(?self $other): self
    {
        if ($other === null) {
            return $this;
        }

        $reasoning = null;
        if ($this->reasoningTokens !== null || $other->reasoningTokens !== null) {
            $reasoning = ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0);
        }

        $cached = null;
        if ($this->cachedTokens !== null || $other->cachedTokens !== null) {
            $cached = ($this->cachedTokens ?? 0) + ($other->cachedTokens ?? 0);
        }

        $prompt = $this->promptTokens + $other->promptTokens;
        $completion = $this->completionTokens + $other->completionTokens;
        $total = $this->totalTokens + $other->totalTokens;

        return new self(
            promptTokens: $prompt,
            completionTokens: $completion,
            totalTokens: $total > 0 ? $total : ($prompt + $completion),
            reasoningTokens: $reasoning,
            cachedTokens: $cached,
            isComplete: $this->isComplete && $other->isComplete
        );
    }

    /** @return array{prompt: int, completion: int, total: int, reasoning: ?int, cached: ?int, complete?: false} */
    public function toArray(): array
    {
        $data = [
            'prompt' => $this->promptTokens,
            'completion' => $this->completionTokens,
            'total' => $this->totalTokens,
            'reasoning' => $this->reasoningTokens,
            'cached' => $this->cachedTokens,
        ];

        return $this->isComplete ? $data : [...$data, 'complete' => false];
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            promptTokens: (int) ($data['prompt'] ?? $data['prompt_tokens'] ?? 0),
            completionTokens: (int) ($data['completion'] ?? $data['completion_tokens'] ?? 0),
            totalTokens: (int) ($data['total'] ?? $data['total_tokens'] ?? 0),
            reasoningTokens: isset($data['reasoning']) ? (int) $data['reasoning'] : (isset($data['reasoning_tokens']) ? (int) $data['reasoning_tokens'] : null),
            cachedTokens: isset($data['cached']) ? (int) $data['cached'] : (isset($data['cached_tokens']) ? (int) $data['cached_tokens'] : null),
            isComplete: (bool) ($data['complete'] ?? true)
        );
    }
}
