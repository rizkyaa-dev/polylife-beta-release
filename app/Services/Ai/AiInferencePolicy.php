<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;

final class AiInferencePolicy
{
    public function runTimeoutSeconds(ThinkingEffort $effort): int
    {
        return match ($effort) {
            ThinkingEffort::Off => 60,
            ThinkingEffort::Low => 90,
            ThinkingEffort::High => 180,
            ThinkingEffort::Max => 300,
        };
    }

    public function requestOptions(ThinkingEffort $effort, int $remainingSeconds): LlmRequestOptions
    {
        $perRequestTimeout = match ($effort) {
            ThinkingEffort::Off => 30,
            ThinkingEffort::Low => 45,
            ThinkingEffort::High => 75,
            ThinkingEffort::Max => 150,
        };
        $maxOutputTokens = match ($effort) {
            ThinkingEffort::Off => 2048,
            ThinkingEffort::Low => 4096,
            ThinkingEffort::High => 8192,
            ThinkingEffort::Max => 16384,
        };

        return new LlmRequestOptions(
            thinkingEffort: $effort,
            timeoutSeconds: max(5, min($perRequestTimeout, $remainingSeconds)),
            maxOutputTokens: $maxOutputTokens
        );
    }
}
