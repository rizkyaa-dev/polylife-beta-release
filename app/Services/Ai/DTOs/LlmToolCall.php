<?php

namespace App\Services\Ai\DTOs;

class LlmToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?string $argumentError = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];

        if ($this->argumentError !== null) {
            $payload['argument_error'] = $this->argumentError;
        }

        return $payload;
    }
}
