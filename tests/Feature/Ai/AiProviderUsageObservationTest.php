<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRun;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiTokenUsageSummary;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\GeminiLlmClient;
use App\Services\Ai\Providers\OpenAiLlmClient;
use App\Services\Ai\ResilientLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiProviderUsageObservationTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        return [['openai'], ['gemini']];
    }

    #[DataProvider('providers')]
    public function test_unknown_usage_is_not_invented_as_measured_zero(string $provider): void
    {
        $run = $this->runWithProviderBody($provider, $this->body($provider, ['unexpected' => true]));
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->model_calls);
        $this->assertSame(0, $run->measured_model_calls);
        $this->assertSame('unknown', $run->tokenUsageStatus());
        $this->assertSame('unknown', AiTokenUsageSummary::session($run->session_id)['status']);
    }

    #[DataProvider('providers')]
    public function test_explicit_zero_usage_is_a_real_measurement_recorded_once(string $provider): void
    {
        $run = $this->runWithProviderBody($provider, $this->body($provider, $this->usage($provider, 0, 0)));
        $this->assertSame(0, $run->total_tokens);
        $this->assertSame(1, $run->model_calls);
        $this->assertSame(1, $run->measured_model_calls);
        $this->assertSame('complete', $run->tokenUsageStatus());
        $this->assertSame(1, $run->steps()->firstOrFail()->public_metadata['model_calls']);
    }

    public static function rejectedResponses(): array
    {
        return [
            ['openai', ['choices' => [['message' => ['refusal' => 'Cannot answer'], 'finish_reason' => 'stop']]], 'provider_response_blocked'],
            ['openai', ['choices' => [['message' => ['content' => null], 'finish_reason' => 'content_filter']]], 'provider_response_blocked'],
            ['openai', ['choices' => [['message' => ['content' => null], 'finish_reason' => 'stop']]], 'provider_response_invalid'],
            ['openai', ['choices' => []], 'provider_response_invalid'],
            ['gemini', ['candidates' => [['finishReason' => 'SAFETY']]], 'provider_response_blocked'],
            ['gemini', ['promptFeedback' => ['blockReason' => 'SAFETY']], 'provider_response_blocked'],
            ['gemini', ['candidates' => []], 'provider_response_invalid'],
        ];
    }

    #[DataProvider('rejectedResponses')]
    public function test_rejected_response_keeps_available_usage_without_accepting_its_content(string $provider, array $body, string $errorCode): void
    {
        $body[$provider === 'openai' ? 'usage' : 'usageMetadata'] = $this->usage($provider, 100, 10);
        try {
            $this->runWithProviderBody($provider, $body);
            $this->fail('Rejected content was accepted.');
        } catch (AiProviderException $error) {
            $this->assertSame($errorCode, $error->errorCode);
        }
        $run = AiChatRun::firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertSame(110, $run->total_tokens);
        $this->assertSame(1, $run->model_calls);
        $this->assertSame(1, $run->measured_model_calls);
        $this->assertSame('complete', $run->tokenUsageStatus());
        $this->assertSame(110, AiTokenUsageSummary::session($run->session_id)['total']);
        $this->assertSame(110, $run->steps()->firstOrFail()->public_metadata['tokens']['total']);
    }

    public function test_retry_attempts_are_not_double_counted_by_nested_inference_observers(): void
    {
        Http::preventStrayRequests();
        Http::fakeSequence()->push([], 503)->push($this->body('openai', $this->usage('openai', 100, 10)));
        $this->bindClient('openai');
        $run = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Usage')['run'];
        $this->assertSame(2, $run->model_calls);
        $this->assertSame(1, $run->measured_model_calls);
        $this->assertSame(110, $run->total_tokens);
        $this->assertSame('partial', $run->tokenUsageStatus());
        Http::assertSentCount(2);
    }

    public function test_gemini_thoughts_are_persisted_without_double_counting_the_reported_total(): void
    {
        $run = $this->runWithProviderBody('gemini', $this->body('gemini', [
            'promptTokenCount' => 100, 'candidatesTokenCount' => 10,
            'thoughtsTokenCount' => 5, 'cachedContentTokenCount' => 40, 'totalTokenCount' => 115,
        ]));
        $this->assertSame(100, $run->prompt_tokens);
        $this->assertSame(10, $run->completion_tokens);
        $this->assertSame(115, $run->total_tokens);
        $this->assertSame('complete', $run->tokenUsageStatus());
        $tokens = $run->steps()->firstOrFail()->public_metadata['tokens'];
        $this->assertSame(5, $tokens['reasoning']);
        $this->assertSame(40, $tokens['cached']);
        $this->assertSame(115, $tokens['total']);
    }

    public function test_gemini_thoughts_contribute_to_a_partial_total_when_the_provider_omits_total(): void
    {
        $run = $this->runWithProviderBody('gemini', $this->body('gemini', [
            'promptTokenCount' => 100, 'candidatesTokenCount' => 10, 'thoughtsTokenCount' => 5,
        ]));
        $this->assertSame(115, $run->total_tokens);
        $this->assertSame(0, $run->measured_model_calls);
        $this->assertSame('partial', $run->tokenUsageStatus());
        $this->assertSame(5, $run->steps()->firstOrFail()->public_metadata['tokens']['reasoning']);
        $this->assertSame(115, AiTokenUsageSummary::session($run->session_id)['total']);
    }

    #[DataProvider('providers')]
    public function test_partial_usage_survives_without_marking_the_call_complete(string $provider): void
    {
        $usage = $provider === 'openai' ? ['prompt_tokens' => 100] : ['promptTokenCount' => 100];
        $run = $this->runWithProviderBody($provider, $this->body($provider, $usage));
        $this->assertSame(100, $run->total_tokens);
        $this->assertSame(0, $run->measured_model_calls);
        $this->assertSame('partial', $run->tokenUsageStatus());
        $this->assertSame('partial', AiTokenUsageSummary::session($run->session_id)['status']);
        $this->assertFalse($run->steps()->firstOrFail()->public_metadata['tokens']['complete']);
    }

    private function runWithProviderBody(string $provider, array $body): AiChatRun
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($body)]);
        $this->bindClient($provider);

        return app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Usage')['run'];
    }

    private function bindClient(string $provider): void
    {
        $client = $provider === 'openai' ? new OpenAiLlmClient('test-key') : new GeminiLlmClient('test-key');
        $this->app->instance(LlmClientInterface::class, new ResilientLlmClient($client, $provider));
    }

    private function body(string $provider, array $usage): array
    {
        return $provider === 'openai'
            ? ['choices' => [['message' => ['content' => 'Valid answer'], 'finish_reason' => 'stop']], 'usage' => $usage]
            : ['candidates' => [['content' => ['parts' => [['text' => 'Valid answer']]], 'finishReason' => 'STOP']], 'usageMetadata' => $usage];
    }

    private function usage(string $provider, int $prompt, int $completion): array
    {
        return $provider === 'openai'
            ? ['prompt_tokens' => $prompt, 'completion_tokens' => $completion, 'total_tokens' => $prompt + $completion]
            : ['promptTokenCount' => $prompt, 'candidatesTokenCount' => $completion, 'totalTokenCount' => $prompt + $completion];
    }
}
