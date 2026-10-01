<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\DTOs\LlmTokenUsage;
use App\Services\Ai\Providers\LlmTokenUsageParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LlmTokenUsageParserTest extends TestCase
{
    public static function invalidUsage(): array
    {
        return [[null], [[]], [['unknown' => 1]],
            [['prompt_tokens' => -1, 'completion_tokens' => 10]],
            [['prompt_tokens' => '100', 'completion_tokens' => 10]],
            [['prompt_tokens' => true, 'completion_tokens' => 10]],
            [['prompt_tokens' => [], 'completion_tokens' => 10]],
            [['prompt_tokens' => 100.5, 'completion_tokens' => 10]],
            [['prompt_tokens' => 100, 'completion_tokens' => 10, 'total_tokens' => 50]],
            [['prompt_tokens' => PHP_INT_MAX, 'completion_tokens' => 1]],
            [['prompt_tokens' => 4_294_967_296, 'completion_tokens' => 10]],
        ];
    }

    #[DataProvider('invalidUsage')]
    public function test_invalid_or_underdetermined_usage_is_unknown(mixed $usage): void
    {
        $this->assertNull(LlmTokenUsageParser::openAi($usage));
        $mapping = ['prompt_tokens' => 'promptTokenCount', 'completion_tokens' => 'candidatesTokenCount', 'total_tokens' => 'totalTokenCount'];
        $gemini = is_array($usage) ? array_combine(array_map(fn ($key) => $mapping[$key] ?? $key, array_keys($usage)), array_values($usage)) : $usage;
        $this->assertNull(LlmTokenUsageParser::gemini($gemini));
    }

    public function test_omitted_totals_and_known_zero_are_preserved(): void
    {
        $this->assertSame(110, LlmTokenUsageParser::openAi(['prompt_tokens' => 100, 'completion_tokens' => 10])->totalTokens);
        $this->assertSame(0, LlmTokenUsageParser::openAi(['prompt_tokens' => 0, 'completion_tokens' => 0])->totalTokens);
        $gemini = LlmTokenUsageParser::gemini(['promptTokenCount' => 100, 'candidatesTokenCount' => 10, 'thoughtsTokenCount' => 5]);
        $this->assertSame(115, $gemini->totalTokens);
        $this->assertSame(5, $gemini->reasoningTokens);
        $this->assertFalse($gemini->isComplete);
    }

    public function test_partial_usage_preserves_known_counters_without_inventing_missing_components(): void
    {
        $usage = LlmTokenUsageParser::openAi(['prompt_tokens' => 100]);
        $this->assertSame(100, $usage->totalTokens);
        $this->assertFalse($usage->isComplete);
        $gemini = LlmTokenUsageParser::gemini(['promptTokenCount' => 100, 'totalTokenCount' => 115, 'thoughtsTokenCount' => 5]);
        $this->assertSame(0, $gemini->completionTokens);
        $this->assertSame(115, $gemini->totalTokens);
        $this->assertFalse($gemini->isComplete);
        $this->assertFalse(LlmTokenUsage::fromArray($gemini->toArray())->isComplete);
    }

    public function test_bad_optional_counters_do_not_poison_valid_primary_usage(): void
    {
        $usage = LlmTokenUsageParser::openAi(['prompt_tokens' => 100, 'completion_tokens' => 10,
            'completion_tokens_details' => ['reasoning_tokens' => -1], 'prompt_tokens_details' => ['cached_tokens' => 'bad']]);
        $this->assertSame(110, $usage->totalTokens);
        $this->assertNull($usage->reasoningTokens);
        $this->assertNull($usage->cachedTokens);
        $this->assertFalse(LlmTokenUsageParser::gemini(['promptTokenCount' => 100, 'totalTokenCount' => 115, 'thoughtsTokenCount' => 'bad'])->isComplete);
    }

    public function test_gemini_reported_totals_include_thoughts_without_adding_them_twice(): void
    {
        $usage = LlmTokenUsageParser::gemini([
            'promptTokenCount' => 100, 'candidatesTokenCount' => 10, 'thoughtsTokenCount' => 5,
            'totalTokenCount' => 115, 'cachedContentTokenCount' => 40,
        ]);
        $this->assertSame(10, $usage->completionTokens);
        $this->assertSame(5, $usage->reasoningTokens);
        $this->assertSame(115, $usage->totalTokens);
        $this->assertSame(40, $usage->cachedTokens);
        $this->assertTrue($usage->isComplete);
        $this->assertSame(0, LlmTokenUsageParser::gemini([
            'promptTokenCount' => 0, 'candidatesTokenCount' => 0, 'thoughtsTokenCount' => 0, 'totalTokenCount' => 0,
        ])->reasoningTokens);
    }
}
