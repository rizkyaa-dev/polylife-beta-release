<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmTokenUsage;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\LlmInference;
use App\Services\Ai\ResilientLlmClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LlmInferenceTest extends TestCase
{
    public function test_each_transport_attempt_is_observed_without_double_counting_the_success(): void
    {
        Cache::flush();
        config(['services.ai_provider_retries' => 1]);
        $client = new class implements LlmClientInterface
        {
            private int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                if (++$this->calls === 1) {
                    throw AiProviderException::forStatus(503);
                }

                return new LlmResponse('Done', usage: new LlmTokenUsage(100, 10, 110));
            }
        };
        $usage = [];
        $options = (new LlmRequestOptions)->withUsageObserver(function ($value) use (&$usage): void {
            $usage[] = $value;
        });
        (new LlmInference(new ResilientLlmClient($client, 'usage-test')))->chat([], options: $options);
        $this->assertCount(2, $usage);
        $this->assertNull($usage[0]);
        $this->assertSame(110, $usage[1]->totalTokens);
    }

    public function test_billed_response_is_observed_before_a_late_cancellation_rejects_it(): void
    {
        Cache::flush();
        $active = true;
        $client = new class($active) implements LlmClientInterface
        {
            public function __construct(private bool &$active) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->active = false;

                return new LlmResponse('Late', usage: new LlmTokenUsage(100, 10, 110));
            }
        };
        $usage = [];
        $options = (new LlmRequestOptions)->withUsageObserver(function ($value) use (&$usage): void {
            $usage[] = $value;
        })
            ->withGuard(function () use (&$active): void {
                if (! $active) {
                    throw new AiRunCancelledException;
                }
            });
        try {
            (new LlmInference(new ResilientLlmClient($client, 'usage-cancel')))->chat([], options: $options);
            $this->fail('Late result was accepted.');
        } catch (AiRunCancelledException) {
            $this->assertCount(1, $usage);
            $this->assertSame(110, $usage[0]->totalTokens);
        }
    }
}
