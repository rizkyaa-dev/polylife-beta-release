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

class OpenAiLlmClient implements LlmClientInterface
{
    public function __construct(
        protected readonly ?string $apiKey = null,
        protected readonly string $model = 'gpt-4o-mini',
        protected readonly string $baseUrl = 'https://api.openai.com/v1',
        protected readonly int $timeoutSeconds = 30
    ) {}

    protected function providerName(): string
    {
        return 'OpenAI';
    }

    public function chat(
        array $messages,
        array $tools = [],
        ?string $systemInstruction = null,
        ?LlmRequestOptions $options = null
    ): LlmResponse {
        $options ??= new LlmRequestOptions;
        $providerKey = strtolower($this->providerName());
        $key = $this->apiKey ?: config("services.{$providerKey}.api_key");
        if (empty($key)) {
            throw new RuntimeException(strtoupper($this->providerName()).'_API_KEY tidak dikonfigurasi.');
        }

        $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';

        $formattedMessages = [];
        if ($systemInstruction) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $systemInstruction,
            ];
        }

        foreach ($this->formatMessages(
            $messages,
            $this->shouldIncludeReasoningContent(! empty($tools), $options)
        ) as $msg) {
            $formattedMessages[] = $msg;
        }

        $payload = [
            'model' => $this->model,
            'messages' => $formattedMessages,
        ];

        if ($options->maxOutputTokens !== null) {
            $payload['max_tokens'] = $options->maxOutputTokens;
        }

        if (! empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
            $payload['tool_choice'] = 'auto';
        }

        $payload = $this->preparePayload($payload, $options, ! empty($tools));

        try {
            $response = Http::connectTimeout(5)
                ->timeout($options->timeoutSeconds ?? $this->timeoutSeconds)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$key,
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
        $usage = LlmTokenUsageParser::openAi($data['usage'] ?? null);
        $options->usageObserver?->__invoke($usage);
        $choices = LlmResponseValidator::list($data['choices'] ?? null);
        $choice = LlmResponseValidator::object($choices[0] ?? null);
        $finishReason = LlmResponseValidator::optionalText($choice['finish_reason'] ?? null);
        $choiceMessage = LlmResponseValidator::object($choice['message'] ?? null);
        if (filled($choiceMessage['refusal'] ?? null) || ($choice['finish_reason'] ?? null) === 'content_filter') {
            throw AiProviderException::blockedResponse();
        }
        $content = LlmResponseValidator::optionalText($choiceMessage['content'] ?? null);
        $reasoningContent = LlmResponseValidator::optionalText($choiceMessage['reasoning_content'] ?? null);
        $toolCalls = [];
        if (isset($choiceMessage['tool_calls'])) {
            foreach (LlmResponseValidator::list($choiceMessage['tool_calls']) as $tc) {
                $tc = LlmResponseValidator::object($tc);
                $function = LlmResponseValidator::object($tc['function'] ?? null);
                if (! is_string($function['name'] ?? null) || blank($function['name'])) {
                    throw AiProviderException::invalidResponse();
                }
                $rawArgs = $function['arguments'] ?? '{}';
                $argumentError = null;
                if (is_array($rawArgs)) {
                    $decodedArgs = $rawArgs === [] || ! array_is_list($rawArgs) ? $rawArgs : [];
                    $argumentError = $rawArgs !== [] && array_is_list($rawArgs) ? 'Argumen tool dari penyedia AI bukan objek JSON yang valid.' : null;
                } else {
                    $decoded = json_decode((string) $rawArgs, true);
                    if (! is_array($decoded) || ! str_starts_with(ltrim((string) $rawArgs), '{')) {
                        $decodedArgs = [];
                        $argumentError = 'Argumen tool dari penyedia AI bukan objek JSON yang valid.';
                    } else {
                        $decodedArgs = $decoded;
                    }
                }

                $toolCalls[] = new LlmToolCall(
                    id: LlmResponseValidator::optionalText($tc['id'] ?? null) ?? (string) Str::uuid(),
                    name: (string) ($function['name'] ?? ''),
                    arguments: $decodedArgs,
                    argumentError: $argumentError
                );
            }
        }

        return LlmResponseValidator::usable(new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            reasoningContent: is_string($reasoningContent) ? $reasoningContent : null,
            usage: $usage
        ));
    }

    /** @param array<string, mixed> $payload */
    protected function preparePayload(array $payload, LlmRequestOptions $options, bool $hasTools): array
    {
        return $payload;
    }

    protected function shouldIncludeReasoningContent(bool $hasTools, LlmRequestOptions $options): bool
    {
        return false;
    }

    /**
     * @param  list<LlmMessage>  $messages
     * @return list<array<string, mixed>>
     */
    protected function formatMessages(array $messages, bool $includeReasoningContent = false): array
    {
        $contents = [];

        foreach ($messages as $msg) {
            if ($msg->role === 'tool') {
                $contents[] = [
                    'role' => 'tool',
                    'tool_call_id' => $msg->toolResult['call_id'] ?? $msg->toolResult['id'] ?? 'call_result',
                    'content' => json_encode($msg->toolResult['result'] ?? $msg->toolResult, JSON_UNESCAPED_UNICODE),
                ];

                continue;
            }

            if ($msg->role === 'assistant') {
                $assistantPayload = [
                    'role' => 'assistant',
                    'content' => $msg->content ?? '',
                ];

                if (! empty($msg->toolCalls)) {
                    $assistantPayload['tool_calls'] = array_map(function (LlmToolCall $call) {
                        return [
                            'id' => $call->id,
                            'type' => 'function',
                            'function' => [
                                'name' => $call->name,
                                'arguments' => json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                            ],
                        ];
                    }, $msg->toolCalls);
                }

                if ($includeReasoningContent && $msg->reasoningContent !== null) {
                    $assistantPayload['reasoning_content'] = $msg->reasoningContent;
                }

                $contents[] = $assistantPayload;

                continue;
            }

            // User or System role
            $contents[] = [
                'role' => $msg->role,
                'content' => (string) ($msg->content ?? ''),
            ];
        }

        return $contents;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     */
    private function formatTools(array $tools): array
    {
        $openaiTools = [];

        foreach ($tools as $tool) {
            $openaiTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'] ?? '',
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ];
        }

        return $openaiTools;
    }
}
