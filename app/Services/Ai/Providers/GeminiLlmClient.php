<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gemini-2.5-flash',
        private readonly int $timeoutSeconds = 30
    ) {}

    public function chat(
        array $messages,
        array $tools = [],
        ?string $systemInstruction = null,
        ?LlmRequestOptions $options = null
    ): LlmResponse {
        $key = $this->apiKey ?: config('services.gemini.api_key');
        if (empty($key)) {
            throw new RuntimeException('GEMINI_API_KEY tidak dikonfigurasi.');
        }

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $this->model
        );

        $payload = [
            'contents' => $this->formatMessages($messages),
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        if (! empty($tools)) {
            $payload['tools'] = [
                [
                    'functionDeclarations' => $this->formatTools($tools),
                ],
            ];
        }

        if ($options?->maxOutputTokens !== null) {
            $payload['generationConfig']['maxOutputTokens'] = $options->maxOutputTokens;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout($options?->timeoutSeconds ?? $this->timeoutSeconds)
                ->withHeaders([
                    'x-goog-api-key' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw AiProviderException::transportFailure($exception);
        }

        if ($response->failed()) {
            throw AiProviderException::forStatus($response->status());
        }

        $data = LlmResponseValidator::object($response->json());
        $usage = LlmTokenUsageParser::gemini($data['usageMetadata'] ?? null);
        $options?->usageObserver?->__invoke($usage);
        $feedback = isset($data['promptFeedback']) ? LlmResponseValidator::object($data['promptFeedback']) : [];
        if (filled($feedback['blockReason'] ?? null)) {
            throw AiProviderException::blockedResponse();
        }
        $candidates = LlmResponseValidator::list($data['candidates'] ?? null);
        $candidate = LlmResponseValidator::object($candidates[0] ?? null);
        $finishReason = LlmResponseValidator::optionalText($candidate['finishReason'] ?? null);
        if (in_array($finishReason, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) {
            throw AiProviderException::blockedResponse();
        }
        $candidateContent = ! isset($candidate['content']) && $finishReason === 'MAX_TOKENS'
            ? ['parts' => []] : LlmResponseValidator::object($candidate['content'] ?? null);
        $parts = LlmResponseValidator::list($candidateContent['parts'] ?? null);
        $content = null;
        $toolCalls = [];

        foreach ($parts as $part) {
            $part = LlmResponseValidator::object($part);
            if (isset($part['text'])) {
                $content = ($content !== null ? $content."\n" : '').LlmResponseValidator::optionalText($part['text']);
            }

            if (isset($part['functionCall'])) {
                $fc = LlmResponseValidator::object($part['functionCall']);
                if (! is_string($fc['name'] ?? null) || blank($fc['name'])) {
                    throw AiProviderException::invalidResponse();
                }
                $toolCalls[] = new LlmToolCall(
                    id: LlmResponseValidator::optionalText($fc['id'] ?? null) ?? (string) Str::uuid(),
                    name: (string) ($fc['name'] ?? ''),
                    arguments: is_array($fc['args'] ?? null) ? $fc['args'] : [],
                    argumentError: isset($fc['args']) && ! is_array($fc['args'])
                        ? 'Argumen tool dari penyedia AI bukan objek yang valid.'
                        : null
                );
            }
        }

        return LlmResponseValidator::usable(new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage
        ));
    }

    /**
     * Gemini expects its schema type enum in uppercase, while the internal
     * tool contract follows standard lowercase JSON Schema.
     *
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     */
    private function formatTools(array $tools): array
    {
        return array_map(fn (array $tool): array => $this->normalizeSchemaTypes($tool), $tools);
    }

    /** @return array<string, mixed> */
    private function normalizeSchemaTypes(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'type' && is_string($item)) {
                $value[$key] = strtoupper($item);
            } elseif (is_array($item)) {
                $value[$key] = $this->normalizeSchemaTypes($item);
            }
        }

        return $value;
    }

    /**
     * @param  list<LlmMessage>  $messages
     * @return list<array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg->role === 'assistant' ? 'model' : 'user';

            if ($msg->role === 'tool') {
                $functionResponse = [
                    'name' => $msg->toolResult['tool_name'] ?? 'unknown',
                    'response' => $msg->toolResult['result'] ?? [],
                ];
                if (filled($msg->toolResult['call_id'] ?? null)) {
                    $functionResponse['id'] = $msg->toolResult['call_id'];
                }
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => $functionResponse,
                        ],
                    ],
                ];

                continue;
            }

            $parts = [];
            if ($msg->content !== null && $msg->content !== '') {
                $parts[] = ['text' => $msg->content];
            }

            foreach ($msg->toolCalls as $call) {
                $parts[] = [
                    'functionCall' => [
                        'id' => $call->id,
                        'name' => $call->name,
                        'args' => $call->arguments,
                    ],
                ];
            }

            if (! empty($parts)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => $parts,
                ];
            }
        }

        return $contents;
    }
}
