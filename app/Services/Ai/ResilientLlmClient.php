<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ResilientLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly LlmClientInterface $client,
        private readonly string $provider
    ) {}

    public function chat(
        array $messages,
        array $tools = [],
        ?string $systemInstruction = null,
        ?LlmRequestOptions $options = null
    ): LlmResponse {
        $this->ensureCircuitIsClosed();
        $options ??= new LlmRequestOptions(timeoutSeconds: 30);
        ($options->ensureActive ?? static fn () => null)();
        $deadlineAt = $options->deadlineAt ?? $this->monotonicTime() + ($options->timeoutSeconds ?? 30);
        $remaining = $deadlineAt - $this->monotonicTime();
        if ($remaining < 1) {
            throw AiProviderException::timeout();
        }
        // The slot covers the complete operation, including backoff and retries.
        $slot = $this->acquireConcurrencySlot((int) ceil($remaining));
        $maxRetries = max(0, (int) config('services.ai_provider_retries', 1));
        $startedAt = microtime(true);

        try {
            for ($attempt = 0; ; $attempt++) {
                try {
                    ($options->ensureActive ?? static fn () => null)();
                    $remaining = (int) floor($deadlineAt - $this->monotonicTime());
                    if ($remaining < 1) {
                        throw AiProviderException::timeout();
                    }
                    $attemptOptions = $options->forAttempt(min($options->timeoutSeconds ?? 30, $remaining), $deadlineAt);
                    $response = $this->client->chat($messages, $tools, $systemInstruction, $attemptOptions);
                    ($options->ensureActive ?? static fn () => null)();
                    if ($this->monotonicTime() >= $deadlineAt) {
                        throw AiProviderException::timeout();
                    }
                    $this->recordSuccess();
                    if ($this->shouldSampleSuccess()) {
                        Log::info('ai.provider.completed', $this->metricContext($startedAt, $attempt + 1));
                    }

                    return $response;
                } catch (AiProviderException $exception) {
                    $this->recordFailure($exception);
                    $delay = random_int(250_000, 500_000) * (2 ** min($attempt, 4));
                    if (! $this->canRetryAutomatically($exception) || $attempt >= $maxRetries
                        || $deadlineAt - $this->monotonicTime() < 1 + $delay / 1_000_000) {
                        Log::warning('ai.provider.failed', $this->metricContext($startedAt, $attempt + 1) + [
                            'error_code' => $exception->errorCode,
                        ]);

                        throw $exception;
                    }

                    usleep($delay);
                    $this->ensureCircuitIsClosed();
                }
            }
        } finally {
            $slot->release();
        }
    }

    private function canRetryAutomatically(AiProviderException $exception): bool
    {
        // Ambiguous transport failures may still be generating upstream. User retry
        // is supported, but automatic replay must not duplicate that generation.
        return $exception->retryable && in_array($exception->errorCode, [
            'provider_rate_limited', 'provider_unavailable',
        ], true);
    }

    private function monotonicTime(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    private function ensureCircuitIsClosed(): void
    {
        if (Cache::has($this->cacheKey('circuit-open'))) {
            throw AiProviderException::circuitOpen();
        }
    }

    private function acquireConcurrencySlot(int $timeoutSeconds): Lock
    {
        $slots = max(1, (int) config('services.ai_provider_max_concurrency', 20));
        $owner = (string) str()->uuid();

        for ($index = 0; $index < $slots; $index++) {
            $lock = Cache::lock($this->cacheKey("slot:{$index}"), $timeoutSeconds + 15, $owner);
            if ($lock->get()) {
                return $lock;
            }
        }

        throw AiProviderException::overloaded();
    }

    private function recordSuccess(): void
    {
        Cache::forget($this->cacheKey('failures'));
        Cache::forget($this->cacheKey('circuit-open'));
    }

    private function recordFailure(AiProviderException $exception): void
    {
        if (! $exception->retryable || $exception->errorCode === 'provider_overloaded') {
            return;
        }

        $failureKey = $this->cacheKey('failures');
        Cache::add($failureKey, 0, now()->addMinutes(5));
        $failures = (int) Cache::increment($failureKey);
        $threshold = max(2, (int) config('services.ai_circuit_failure_threshold', 5));

        if ($failures >= $threshold) {
            Cache::put(
                $this->cacheKey('circuit-open'),
                true,
                now()->addSeconds(max(5, (int) config('services.ai_circuit_cooldown_seconds', 30)))
            );
        }
    }

    /** @return array<string, int|string> */
    private function metricContext(float $startedAt, int $attempts): array
    {
        return [
            'provider' => $this->provider,
            'attempts' => $attempts,
            'duration_ms' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
        ];
    }

    private function cacheKey(string $suffix): string
    {
        return "ai:provider:{$this->provider}:{$suffix}";
    }

    private function shouldSampleSuccess(): bool
    {
        $rate = min(1, max(0, (float) config('services.ai_provider_success_log_sample', 0.05)));

        return $rate >= 1 || ($rate > 0 && random_int(1, 10_000) <= (int) round($rate * 10_000));
    }
}
