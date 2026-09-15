<?php

namespace App\Services\Ai\DTOs;

use App\Services\Ai\Enums\ThinkingEffort;

final class LlmRequestOptions
{
    public function __construct(
        public readonly ThinkingEffort $thinkingEffort = ThinkingEffort::High,
        public readonly ?int $timeoutSeconds = null,
        public readonly ?int $maxOutputTokens = null
    ) {}
}
