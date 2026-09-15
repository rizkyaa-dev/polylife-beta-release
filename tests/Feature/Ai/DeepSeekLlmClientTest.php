<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Providers\DeepSeekLlmClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeepSeekLlmClientTest extends TestCase
{
    public function test_deepseek_client_sends_payload_to_deepseek_api_and_parses_response(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'deepseek-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Halo dari DeepSeek!',
                            'reasoning_content' => 'Saya perlu menjawab sapaan secara singkat.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $client = new DeepSeekLlmClient(
            apiKey: 'sk-deepseek-test-key',
            model: 'deepseek-flash',
            baseUrl: 'https://api.deepseek.com'
        );

        $messages = [
            new LlmMessage(role: 'user', content: 'Halo DeepSeek!'),
        ];

        $response = $client->chat($messages, [], 'Instruksi sistem kampus.');

        $this->assertEquals('Halo dari DeepSeek!', $response->content);
        $this->assertEquals('Saya perlu menjawab sapaan secara singkat.', $response->reasoningContent);
        $this->assertEmpty($response->toolCalls);
        $this->assertEquals('stop', $response->finishReason);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-deepseek-test-key')
                && $data['model'] === 'deepseek-flash'
                && $data['thinking'] === ['type' => 'enabled']
                && $data['reasoning_effort'] === 'high'
                && $data['messages'][0]['role'] === 'system'
                && $data['messages'][0]['content'] === 'Instruksi sistem kampus.'
                && $data['messages'][1]['role'] === 'user'
                && $data['messages'][1]['content'] === 'Halo DeepSeek!';
        });
    }

    public function test_deepseek_client_parses_tool_calls_accurately(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'deepseek-456',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'deepseek-flash',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'reasoning_content' => 'Saya perlu memakai tool to-do.',
                            'tool_calls' => [
                                [
                                    'id' => 'call_ds_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'create_todolist',
                                        'arguments' => json_encode([
                                            'judul' => 'Kerjakan Laporan Praktikum',
                                            'prioritas' => 'tinggi',
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                        'finish_reason' => 'tool_calls',
                    ],
                ],
            ], 200),
        ]);

        $client = new DeepSeekLlmClient(apiKey: 'sk-deepseek-key');

        $tools = [
            [
                'name' => 'create_todolist',
                'description' => 'Membuat tugas to-do',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'judul' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $response = $client->chat([
            new LlmMessage(role: 'user', content: 'Apa yang bisa kamu lakukan?'),
            new LlmMessage(
                role: 'assistant',
                content: 'Saya dapat membantu mengelola tugas.',
                reasoningContent: 'Saya menjelaskan kemampuan yang tersedia.'
            ),
            new LlmMessage(role: 'user', content: 'Tambah to-do tugas'),
        ], $tools);

        $this->assertCount(1, $response->toolCalls);
        $this->assertEquals('call_ds_1', $response->toolCalls[0]->id);
        $this->assertEquals('create_todolist', $response->toolCalls[0]->name);
        $this->assertEquals('Kerjakan Laporan Praktikum', $response->toolCalls[0]->arguments['judul']);
        $this->assertEquals('tinggi', $response->toolCalls[0]->arguments['prioritas']);
        $this->assertEquals('Saya perlu memakai tool to-do.', $response->reasoningContent);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['model'] === 'deepseek-flash'
                && $data['thinking'] === ['type' => 'enabled']
                && $data['reasoning_effort'] === 'high'
                && ! array_key_exists('tool_choice', $data)
                && $data['messages'][1]['reasoning_content'] === 'Saya menjelaskan kemampuan yang tersedia.';
        });
    }

    public function test_deepseek_client_can_disable_thinking_per_request(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Jawaban cepat.'],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $client = new DeepSeekLlmClient(apiKey: 'sk-deepseek-key');
        $response = $client->chat(
            [new LlmMessage(role: 'user', content: 'Jawab cepat')],
            [],
            null,
            new LlmRequestOptions(ThinkingEffort::Off)
        );

        $this->assertSame('Jawaban cepat.', $response->content);
        Http::assertSent(fn ($request) => $request['thinking'] === ['type' => 'disabled']
            && $request['reasoning_effort'] === 'none');
    }

    public function test_deepseek_client_applies_per_request_output_budget(): void
    {
        Http::fake(['https://api.deepseek.com/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        ])]);

        (new DeepSeekLlmClient(apiKey: 'test'))->chat(
            [new LlmMessage(role: 'user', content: 'Analisis')],
            [],
            null,
            new LlmRequestOptions(ThinkingEffort::Max, 120, 16384)
        );

        Http::assertSent(fn ($request): bool => $request['reasoning_effort'] === 'max'
            && $request['max_tokens'] === 16384);
    }

    public function test_deepseek_client_throws_exception_on_error(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Insufficient balance',
                    'type' => 'insufficient_quota',
                ],
            ], 402),
        ]);

        $client = new DeepSeekLlmClient(apiKey: 'sk-deepseek-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Penyedia AI gagal memproses permintaan.');

        $client->chat([new LlmMessage(role: 'user', content: 'Halo')]);
    }
}
