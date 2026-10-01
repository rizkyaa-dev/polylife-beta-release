<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\DTOs\LlmTokenUsage;

/** Untrusted provider counters must never become invented measurements. */
final class LlmTokenUsageParser
{
    private const MAX_COUNTER = 4_294_967_295;

    public static function openAi(mixed $usage): ?LlmTokenUsage
    {
        if (! is_array($usage)) {
            return null;
        }

        return self::parse(
            $usage['prompt_tokens'] ?? null,
            $usage['completion_tokens'] ?? null,
            $usage['total_tokens'] ?? null,
            self::counter(data_get($usage, 'completion_tokens_details.reasoning_tokens')),
            self::counter(data_get($usage, 'prompt_tokens_details.cached_tokens'))
        );
    }

    public static function gemini(mixed $usage): ?LlmTokenUsage
    {
        if (! is_array($usage)) {
            return null;
        }

        return self::parse(
            $usage['promptTokenCount'] ?? null,
            $usage['candidatesTokenCount'] ?? null,
            $usage['totalTokenCount'] ?? null,
            self::counter($usage['thoughtsTokenCount'] ?? null),
            self::counter($usage['cachedContentTokenCount'] ?? null),
            reasoningIncludedInCompletion: false
        );
    }

    private static function parse(mixed $prompt, mixed $completion, mixed $total, ?int $reasoning, ?int $cached, bool $reasoningIncludedInCompletion = true): ?LlmTokenUsage
    {
        foreach ([$prompt, $completion, $total] as $counter) {
            if ($counter !== null && self::counter($counter) === null) {
                return null;
            }
        }

        if ($prompt === null && $completion === null && $total === null) {
            return null;
        }
        $complete = $prompt !== null && $completion !== null && ($total !== null || $reasoningIncludedInCompletion);
        $extra = $reasoningIncludedInCompletion ? 0 : ($reasoning ?? 0);
        $prompt ??= 0;
        $completion ??= 0;
        $minimumTotal = $prompt + $completion;
        $total ??= $minimumTotal + $extra;
        if ($total < $minimumTotal || $total > self::MAX_COUNTER) {
            return null;
        }

        return new LlmTokenUsage($prompt, $completion, $total, $reasoning, $cached, isComplete: $complete);
    }

    private static function counter(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 && $value <= self::MAX_COUNTER ? $value : null;
    }
}
