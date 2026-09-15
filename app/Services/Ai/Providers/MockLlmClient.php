<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use Illuminate\Support\Str;

class MockLlmClient implements LlmClientInterface
{
    /**
     * @var list<LlmResponse>
     */
    private array $queuedResponses = [];

    public function queueResponse(LlmResponse $response): self
    {
        $this->queuedResponses[] = $response;

        return $this;
    }

    public function chat(
        array $messages,
        array $tools = [],
        ?string $systemInstruction = null,
        ?LlmRequestOptions $options = null
    ): LlmResponse {
        if (! empty($this->queuedResponses)) {
            return array_shift($this->queuedResponses);
        }

        $lastMessage = end($messages);
        $userText = strtolower((string) ($lastMessage?->content ?? ''));

        // Deterministic heuristics for testing without external LLM
        if (str_contains($userText, 'catat pengeluaran') || str_contains($userText, 'beli')) {
            return new LlmResponse(
                content: null,
                toolCalls: [
                    new LlmToolCall(
                        id: (string) Str::uuid(),
                        name: 'create_keuangan',
                        arguments: [
                            'jenis' => 'pengeluaran',
                            'kategori' => 'Konsumsi',
                            'nominal' => 25000,
                            'tanggal' => now()->toDateString(),
                            'deskripsi' => 'Makan siang',
                        ]
                    ),
                ]
            );
        }

        if (str_contains($userText, 'jadwal') || str_contains($userText, 'kuliah')) {
            return new LlmResponse(
                content: null,
                toolCalls: [
                    new LlmToolCall(
                        id: (string) Str::uuid(),
                        name: 'get_upcoming_schedule',
                        arguments: [
                            'start_date' => now()->toDateString(),
                            'end_date' => now()->addDays(7)->toDateString(),
                        ]
                    ),
                ]
            );
        }

        if (str_contains($userText, 'uang') || str_contains($userText, 'pengeluaran') || str_contains($userText, 'saldo')) {
            return new LlmResponse(
                content: null,
                toolCalls: [
                    new LlmToolCall(
                        id: (string) Str::uuid(),
                        name: 'get_financial_summary',
                        arguments: [
                            'month' => now()->format('Y-m'),
                        ]
                    ),
                ]
            );
        }

        return new LlmResponse('Halo! Saya asisten PolyLife siap membantu mengelola jadwal, tugas, dan keuangan Anda.');
    }
}
