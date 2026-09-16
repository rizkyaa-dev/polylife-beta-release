<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class CreateKegiatanTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'create_kegiatan';
    }

    public function description(): string
    {
        return 'Mengusulkan sub-kegiatan di dalam jadwal yang sudah ada. Jangan membuat jadwal baru bila pengguna menyebut jadwal induk.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'jadwal' => ['type' => 'string', 'description' => 'Judul jadwal induk'],
                    'nama_kegiatan' => ['type' => 'string'], 'lokasi' => ['type' => 'string'],
                    'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'waktu' => ['type' => 'string', 'description' => 'HH:mm'],
                ],
                'required' => ['jadwal', 'nama_kegiatan', 'tanggal', 'waktu'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Jadwal $schedule */
        $schedule = $this->resolver->resolve($user, Jadwal::class, ['title'], (string) ($arguments['jadwal'] ?? ''), 'jadwal');
        $name = trim((string) ($arguments['nama_kegiatan'] ?? ''));
        if ($name === '' || empty($arguments['tanggal']) || empty($arguments['waktu'])) {
            throw new AiActionException('Nama, tanggal, dan waktu kegiatan wajib diisi.');
        }
        $date = $this->timeContext->parse($user, $arguments['tanggal'] ?? null)->toDateString();
        $time = $this->timeContext->parse($user, $date.' '.($arguments['waktu'] ?? ''))->format('H:i');

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Kegiatan {$name} di {$schedule->title}",
            'payload' => [
                'jadwal_id' => $schedule->id, 'nama_kegiatan' => $name,
                'lokasi' => trim((string) ($arguments['lokasi'] ?? '')) ?: null,
                'tanggal_deadline' => $date, 'waktu' => $time, 'status' => 'belum_dimulai',
            ],
        ];
    }
}
