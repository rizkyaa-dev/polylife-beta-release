<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmTokenUsage;
use Throwable;

/** Observe every model call before agent validation or a structural repair. */
final class LlmInference
{
    public function __construct(private readonly LlmClientInterface $client) {}

    public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
    {
        // Decorators may observe individual transport attempts. Forward those events
        // and only supply a fallback when the wrapped client observed none.
        $observed = false;
        $observer = $options?->usageObserver;
        if ($observer !== null) {
            $options = $options->withUsageObserver(function (?LlmTokenUsage $usage) use (&$observed, $observer): void {
                $observed = true;
                $observer($usage);
            });
        }
        try {
            $response = $this->client->chat($messages, $tools, $systemInstruction, $options);
        } catch (Throwable $exception) {
            if (! $observed) {
                $observer?->__invoke(null);
            }
            throw $exception;
        }
        if (! $observed) {
            $observer?->__invoke($response->usage);
        }

        return $response;
    }
}
