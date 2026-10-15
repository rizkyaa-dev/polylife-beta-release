<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;

final class AiInferencePolicy
{
    public function scienceRunTimeoutSeconds(ThinkingEffort $effort): int
    {
        return $this->runTimeoutSeconds($effort, true);
    }

    public function runTimeoutSeconds(ThinkingEffort $effort, bool $coding = false): int
    {
        if ($coding) {
            return match ($effort) {
                ThinkingEffort::Off => 180,
                ThinkingEffort::Low => 480,
                ThinkingEffort::High => 720,
                ThinkingEffort::Max => 960,
            };
        }

        return match ($effort) {
            ThinkingEffort::Off => 60,
            ThinkingEffort::Low => 180,
            ThinkingEffort::High => 360,
            ThinkingEffort::Max => 600,
        };
    }

    public function requestOptions(
        ThinkingEffort $effort,
        int $remainingSeconds,
        int $minimumOutputTokens = 0,
        bool $coding = false
    ): LlmRequestOptions {
        $perRequestTimeout = match ($effort) {
            ThinkingEffort::Off => 30,
            ThinkingEffort::Low => 90,
            ThinkingEffort::High => 150,
            ThinkingEffort::Max => 300,
        };
        $maxOutputTokens = match ($effort) {
            ThinkingEffort::Off => 2048,
            ThinkingEffort::Low => 4096,
            ThinkingEffort::High => 8192,
            ThinkingEffort::Max => 16384,
        };
        $maxOutputTokens = max($maxOutputTokens, min(16384, max(0, $minimumOutputTokens)));
        if ($coding) {
            // Writing a complete artifact is a different task from planning it.
            $perRequestTimeout = match ($effort) {
                ThinkingEffort::Off => 120,
                ThinkingEffort::Low => 360,
                ThinkingEffort::High => 480,
                ThinkingEffort::Max => 720,
            };
        }

        return new LlmRequestOptions(
            thinkingEffort: $effort,
            timeoutSeconds: max(1, min($perRequestTimeout, $remainingSeconds)),
            maxOutputTokens: $maxOutputTokens
        );
    }
}
