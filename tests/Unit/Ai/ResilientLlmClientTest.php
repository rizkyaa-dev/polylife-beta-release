<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\ResilientLlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResilientLlmClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.ai_provider_retries' => 1,
            'services.ai_provider_max_concurrency' => 2,
            'services.ai_circuit_failure_threshold' => 2,
            'services.ai_circuit_cooldown_seconds' => 30,
        ]);
    }

    public function test_it_retries_a_transient_provider_failure_once(): void
    {
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                if (++$this->calls === 1) {
                    throw AiProviderException::forStatus(503);
                }

                return new LlmResponse(content: 'pulih');
            }
        };

        $response = (new ResilientLlmClient($client, 'test'))->chat([]);

        $this->assertSame('pulih', $response->content);
        $this->assertSame(2, $client->calls);
    }

    public function test_it_opens_the_circuit_after_repeated_retryable_failures(): void
    {
        config(['services.ai_provider_retries' => 0]);
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                throw AiProviderException::forStatus(503);
            }
        };
        $resilient = new ResilientLlmClient($client, 'test');

        foreach (range(1, 2) as $_) {
            try {
                $resilient->chat([]);
            } catch (AiProviderException) {
                // Expected while the failure threshold is reached.
            }
        }

        try {
            $resilient->chat([]);
            $this->fail('Circuit breaker should reject the request.');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_circuit_open', $exception->errorCode);
        }
        $this->assertSame(2, $client->calls);
    }

    public function test_ambiguous_timeout_is_not_replayed_automatically(): void
    {
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                throw AiProviderException::timeout();
            }
        };
        try {
            (new ResilientLlmClient($client, 'test'))->chat([]);
            $this->fail('Expected timeout');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_timeout', $exception->errorCode);
        }
        $this->assertSame(1, $client->calls);
    }

    public function test_expired_budget_does_not_call_the_provider(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->never())->method('chat');
        $this->expectException(AiProviderException::class);
        (new ResilientLlmClient($client, 'test'))->chat([], [], null,
            new LlmRequestOptions(deadlineAt: hrtime(true) / 1_000_000_000 - 1));
    }

    public function test_retry_timeout_is_reduced_by_elapsed_operation_time(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $timeouts = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->timeouts[] = $options->timeoutSeconds;
                if (count($this->timeouts) === 1) {
                    usleep(1_100_000);
                    throw AiProviderException::forStatus(503);
                }

                return new LlmResponse('complete');
            }
        };
        (new ResilientLlmClient($client, 'test'))->chat([], [], null,
            new LlmRequestOptions(timeoutSeconds: 30, deadlineAt: hrtime(true) / 1_000_000_000 + 5));
        $this->assertCount(2, $client->timeouts);
        $this->assertLessThan($client->timeouts[0], $client->timeouts[1]);
    }

    public function test_lease_remains_owned_during_retry_after_old_sixty_second_expiry(): void
    {
        config(['services.ai_provider_max_concurrency' => 1]);
        $base = Carbon::now()->startOfSecond();
        Carbon::setTestNow($base);
        $client = new class($base) implements LlmClientInterface
        {
            private int $calls = 0;

            public bool $blocked = false;

            public function __construct(private readonly Carbon $base) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                if (++$this->calls === 1) {
                    throw AiProviderException::forStatus(503);
                }
                Carbon::setTestNow($this->base->copy()->addSeconds(61));
                try {
                    (new ResilientLlmClient($this, 'test'))->chat([], [], null, $options);
                } catch (AiProviderException $exception) {
                    $this->blocked = $exception->errorCode === 'provider_overloaded';
                }

                return new LlmResponse('complete');
            }
        };
        try {
            (new ResilientLlmClient($client, 'test'))->chat([], [], null,
                new LlmRequestOptions(ThinkingEffort::Low, 45, 256, hrtime(true) / 1_000_000_000 + 90));
            $this->assertTrue($client->blocked);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cancellation_after_provider_response_releases_slot_and_rejects_result(): void
    {
        $active = true;
        $client = new class($active) implements LlmClientInterface
        {
            public function __construct(private bool &$active) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->active = false;

                return new LlmResponse('late result');
            }
        };
        $options = (new LlmRequestOptions)->withGuard(function () use (&$active) {
            if (! $active) {
                throw new AiRunCancelledException;
            }
        });
        try {
            (new ResilientLlmClient($client, 'test'))->chat([], [], null, $options);
            $this->fail('Expected cancellation');
        } catch (AiRunCancelledException) {
            $this->assertSame([], Cache::getStore()->locks);
        }
    }

    public function test_cancellation_prevents_retry_after_transient_failure(): void
    {
        $active = true;
        $client = new class($active) implements LlmClientInterface
        {
            public int $calls = 0;

            public function __construct(private bool &$active) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                $this->active = false;
                throw AiProviderException::forStatus(503);
            }
        };
        $options = (new LlmRequestOptions)->withGuard(function () use (&$active) {
            if (! $active) {
                throw new AiRunCancelledException;
            }
        });
        try {
            (new ResilientLlmClient($client, 'test'))->chat([], [], null, $options);
            $this->fail('Expected cancellation');
        } catch (AiRunCancelledException) {
            $this->assertSame(1, $client->calls);
            $this->assertSame([], Cache::getStore()->locks);
        }
    }
}
