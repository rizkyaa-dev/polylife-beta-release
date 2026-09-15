<?php

namespace App\Services\Ai\DTOs;

class LlmMessage
{
    /**
     * @param  list<LlmToolCall>  $toolCalls
     * @param  array<string, mixed>|null  $toolResult
     */
    public function __construct(
        public readonly string $role, // 'user', 'assistant', 'system', 'tool'
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?array $toolResult = null,
        public readonly ?string $reasoningContent = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => array_map(fn (LlmToolCall $t) => $t->toArray(), $this->toolCalls),
            'tool_result' => $this->toolResult,
            'reasoning_content' => $this->reasoningContent,
        ], fn ($val) => $val !== null && $val !== []);
    }
}
