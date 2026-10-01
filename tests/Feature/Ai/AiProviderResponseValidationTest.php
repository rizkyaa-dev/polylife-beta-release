<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\GeminiLlmClient;
use App\Services\Ai\Providers\OpenAiLlmClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiProviderResponseValidationTest extends TestCase
{
    public static function invalidBodies(): array
    {
        return [
            ['openai', 'not-json'], ['gemini', 'not-json'],
            ['openai', ['choices' => []]], ['gemini', ['candidates' => []]],
            ['openai', ['choices' => [['message' => ['content' => null], 'finish_reason' => 'stop']]]],
            ['openai', ['choices' => [['message' => ['content' => ['text' => 'bad']]]]]],
            ['openai', ['choices' => [['message' => ['tool_calls' => [['function' => ['name' => '']]]]]]]],
            ['gemini', ['candidates' => [['content' => ['parts' => []], 'finishReason' => 'STOP']]]],
            ['gemini', ['candidates' => [['content' => ['parts' => [['text' => 42]]]]]]],
            ['gemini', ['candidates' => [['content' => ['parts' => 'bad']]]]],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_http_success_with_invalid_body_is_never_reported_as_success(string $provider, mixed $body): void
    {
        Http::fake(['*' => Http::response($body, 200)]);
        $client = $provider === 'openai' ? new OpenAiLlmClient('test-key') : new GeminiLlmClient('test-key');
        try {
            $client->chat([new LlmMessage('user', 'hello')]);
            $this->fail('Malformed provider body was accepted.');
        } catch (AiProviderException $error) {
            $this->assertSame('provider_response_invalid', $error->errorCode);
        }
    }

    public function test_gemini_safety_block_is_distinct_from_empty_or_truncated_response(): void
    {
        Http::fakeSequence()->push(['promptFeedback' => ['blockReason' => 'SAFETY']])
            ->push(['candidates' => [['finishReason' => 'MAX_TOKENS']]]);
        $client = new GeminiLlmClient('test-key');
        try {
            $client->chat([]);
            $this->fail('Safety refusal was accepted.');
        } catch (AiProviderException $error) {
            $this->assertSame('provider_response_blocked', $error->errorCode);
            $this->assertFalse($error->retryable);
        }
        $this->assertTrue($client->chat([])->isTruncated());
    }

    public function test_invalid_tool_arguments_remain_available_to_the_existing_repair_flow(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['tool_calls' => [
            ['id' => 'call1', 'function' => ['name' => 'manage_tugas', 'arguments' => 'not-json']],
        ]], 'finish_reason' => 'tool_calls']]])]);
        $response = (new OpenAiLlmClient('test-key'))->chat([]);
        $this->assertSame('manage_tugas', $response->toolCalls[0]->name);
        $this->assertNotNull($response->toolCalls[0]->argumentError);
    }
}
