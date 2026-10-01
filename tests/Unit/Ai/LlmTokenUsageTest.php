<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\DTOs\LlmTokenUsage;
use App\Services\Ai\Providers\GeminiLlmClient;
use App\Services\Ai\Providers\OpenAiLlmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmTokenUsageTest extends TestCase
{
    public function test_missing_totals_are_normalized_per_call_before_accumulation(): void
    {
        $first = new LlmTokenUsage(100, 10, 110);
        $second = new LlmTokenUsage(200, 20);
        $this->assertSame(220, $second->totalTokens);
        $this->assertSame(330, $first->accumulate($second)->totalTokens);
        $this->assertSame(220, LlmTokenUsage::fromArray(['prompt' => 200, 'completion' => 20])->totalTokens);
    }

    public function test_token_usage_initialization_and_accumulation(): void
    {
        $u1 = new LlmTokenUsage(
            promptTokens: 100,
            completionTokens: 50,
            totalTokens: 150,
            reasoningTokens: 20,
            cachedTokens: 10
        );

        $this->assertSame(100, $u1->promptTokens);
        $this->assertSame(50, $u1->completionTokens);
        $this->assertSame(150, $u1->totalTokens);
        $this->assertSame(20, $u1->reasoningTokens);
        $this->assertSame(10, $u1->cachedTokens);

        $u2 = new LlmTokenUsage(
            promptTokens: 200,
            completionTokens: 80,
            totalTokens: 280,
            reasoningTokens: 30,
            cachedTokens: 5
        );

        $combined = $u1->accumulate($u2);
        $this->assertSame(300, $combined->promptTokens);
        $this->assertSame(130, $combined->completionTokens);
        $this->assertSame(430, $combined->totalTokens);
        $this->assertSame(50, $combined->reasoningTokens);
        $this->assertSame(15, $combined->cachedTokens);

        $arr = $combined->toArray();
        $this->assertSame([
            'prompt' => 300,
            'completion' => 130,
            'total' => 430,
            'reasoning' => 50,
            'cached' => 15,
        ], $arr);

        $recreated = LlmTokenUsage::fromArray($arr);
        $this->assertNotNull($recreated);
        $this->assertSame(300, $recreated->promptTokens);
        $this->assertSame(130, $recreated->completionTokens);
        $this->assertSame(430, $recreated->totalTokens);
    }

    public function test_openai_client_parses_usage_metadata(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from OpenAI',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 45,
                    'completion_tokens' => 12,
                    'total_tokens' => 57,
                    'completion_tokens_details' => [
                        'reasoning_tokens' => 8,
                    ],
                    'prompt_tokens_details' => [
                        'cached_tokens' => 20,
                    ],
                ],
            ], 200),
        ]);

        $client = new OpenAiLlmClient(apiKey: 'test-key');
        $response = $client->chat([]);

        $this->assertSame('Hello from OpenAI', $response->content);
        $this->assertNotNull($response->usage);
        $this->assertSame(45, $response->usage->promptTokens);
        $this->assertSame(12, $response->usage->completionTokens);
        $this->assertSame(57, $response->usage->totalTokens);
        $this->assertSame(8, $response->usage->reasoningTokens);
        $this->assertSame(20, $response->usage->cachedTokens);
    }

    public function test_gemini_client_parses_usage_metadata(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Hello from Gemini'],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 80,
                    'candidatesTokenCount' => 25,
                    'totalTokenCount' => 115,
                    'cachedContentTokenCount' => 15,
                    'thoughtsTokenCount' => 10,
                ],
            ], 200),
        ]);

        $client = new GeminiLlmClient(apiKey: 'test-key');
        $response = $client->chat([]);

        $this->assertSame('Hello from Gemini', $response->content);
        $this->assertNotNull($response->usage);
        $this->assertSame(80, $response->usage->promptTokens);
        $this->assertSame(25, $response->usage->completionTokens);
        $this->assertSame(115, $response->usage->totalTokens);
        $this->assertSame(15, $response->usage->cachedTokens);
        $this->assertSame(10, $response->usage->reasoningTokens);
    }
}
