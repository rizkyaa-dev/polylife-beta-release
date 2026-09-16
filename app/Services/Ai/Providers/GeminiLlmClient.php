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

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? null;

        if (! $candidate) {
            return new LlmResponse('Tidak ada respons dari AI.');
        }

        $content = null;
        $toolCalls = [];

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $content = ($content !== null ? $content."\n" : '').$part['text'];
            }

            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $toolCalls[] = new LlmToolCall(
                    id: (string) ($fc['id'] ?? Str::uuid()),
                    name: (string) ($fc['name'] ?? ''),
                    arguments: is_array($fc['args'] ?? null) ? $fc['args'] : [],
                    argumentError: isset($fc['args']) && ! is_array($fc['args'])
                        ? 'Argumen tool dari penyedia AI bukan objek yang valid.'
                        : null
                );
            }
        }

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $candidate['finishReason'] ?? null
        );
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
