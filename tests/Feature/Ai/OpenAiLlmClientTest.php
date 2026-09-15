<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\Providers\OpenAiLlmClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiLlmClientTest extends TestCase
{
    public function test_openai_client_sends_formatted_payload_and_parses_text_response(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Halo! Ini respons dari OpenAI.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $client = new OpenAiLlmClient(
            apiKey: 'sk-test-key-openai',
            model: 'gpt-4o-mini',
            baseUrl: 'https://api.openai.com/v1'
        );

        $messages = [
            new LlmMessage(role: 'user', content: 'Halo apa kabar?'),
        ];

        $response = $client->chat($messages, [], 'Kamu adalah asisten pintar.');

        $this->assertEquals('Halo! Ini respons dari OpenAI.', $response->content);
        $this->assertEmpty($response->toolCalls);
        $this->assertEquals('stop', $response->finishReason);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->hasHeader('Authorization', 'Bearer sk-test-key-openai')
                && $data['model'] === 'gpt-4o-mini'
                && $data['messages'][0]['role'] === 'system'
                && $data['messages'][0]['content'] === 'Kamu adalah asisten pintar.'
                && $data['messages'][1]['role'] === 'user'
                && $data['messages'][1]['content'] === 'Halo apa kabar?';
        });
    }

    public function test_openai_client_parses_tool_calls_accurately(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-456',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o-mini',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_abc999',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'create_keuangan',
                                        'arguments' => json_encode([
                                            'jenis' => 'pengeluaran',
                                            'kategori' => 'Makan',
                                            'nominal' => 35000,
                                            'deskripsi' => 'Ayam Bakar',
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

        $client = new OpenAiLlmClient(
            apiKey: 'sk-test-key-openai',
            model: 'gpt-4o-mini'
        );

        $tools = [
            [
                'name' => 'create_keuangan',
                'description' => 'Mencatat keuangan',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nominal' => ['type' => 'number'],
                    ],
                ],
            ],
        ];

        $messages = [
            new LlmMessage(role: 'user', content: 'Catat pengeluaran makan 35rb'),
        ];

        $response = $client->chat($messages, $tools);

        $this->assertCount(1, $response->toolCalls);
        $toolCall = $response->toolCalls[0];
        $this->assertEquals('call_abc999', $toolCall->id);
        $this->assertEquals('create_keuangan', $toolCall->name);
        $this->assertEquals(35000, $toolCall->arguments['nominal']);
        $this->assertEquals('Ayam Bakar', $toolCall->arguments['deskripsi']);
        $this->assertEquals('tool_calls', $response->finishReason);
    }

    public function test_openai_client_throws_exception_on_api_error(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided.',
                    'type' => 'invalid_request_error',
                ],
            ], 401),
        ]);

        $client = new OpenAiLlmClient(apiKey: 'invalid-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Konfigurasi autentikasi penyedia AI ditolak.');

        $client->chat([new LlmMessage(role: 'user', content: 'Halo')]);
    }

    public function test_openai_client_normalizes_scalar_tool_arguments_without_type_error(): void
    {
        Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['tool_calls' => [[
                    'id' => 'call_bad_args',
                    'function' => ['name' => 'get_pending_tasks', 'arguments' => '5'],
                ]]],
                'finish_reason' => 'tool_calls',
            ]],
        ])]);

        $response = (new OpenAiLlmClient(apiKey: 'test'))->chat([
            new LlmMessage(role: 'user', content: 'Lihat tugas'),
        ]);

        $this->assertSame([], $response->toolCalls[0]->arguments);
        $this->assertSame(
            'Argumen tool dari penyedia AI bukan objek JSON yang valid.',
            $response->toolCalls[0]->argumentError
        );
    }
}
