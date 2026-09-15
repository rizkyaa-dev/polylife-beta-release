<?php

namespace App\Services\Ai\DTOs;

class LlmResponse
{
    /**
     * @param  list<LlmToolCall>  $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls = [],
        public readonly ?string $finishReason = null,
        public readonly ?string $reasoningContent = null
    ) {}

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function isTruncated(): bool
    {
        return in_array(strtoupper((string) $this->finishReason), ['LENGTH', 'MAX_TOKENS'], true);
    }
}
