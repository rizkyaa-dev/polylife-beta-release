<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

class CreateCatatanTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'create_catatan';
    }

    public function description(): string
    {
        return 'Mengusulkan pembuatan catatan pribadi baru. Gunakan untuk note, catatan, memo, atau materi tertulis; jangan menggantinya dengan to-do.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'judul' => [
                        'type' => 'string',
                        'description' => 'Judul singkat catatan, maksimal 150 karakter',
                    ],
                    'isi' => [
                        'type' => 'string',
                        'description' => 'Isi lengkap catatan yang harus dipertahankan, bukan diringkas menjadi tugas',
                    ],
                    'tanggal' => [
                        'type' => 'string',
                        'description' => 'Tanggal catatan format YYYY-MM-DD (opsional, default hari ini)',
                    ],
                ],
                'required' => ['judul', 'isi'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $judul = trim((string) ($arguments['judul'] ?? ''));
        $isi = trim((string) ($arguments['isi'] ?? ''));
        $tanggal = array_key_exists('tanggal', $arguments)
            ? $this->timeContext->parse($user, $arguments['tanggal'])->toDateString()
            : $this->timeContext->now($user)->toDateString();

        return [
            'status' => 'proposal_created',
            'tool_name' => $this->name(),
            'summary' => "Catatan: {$judul}",
            'payload' => [
                'judul' => $judul,
                'isi' => $isi,
                'tanggal' => $tanggal,
                'show_preview' => true,
            ],
        ];
    }
}
