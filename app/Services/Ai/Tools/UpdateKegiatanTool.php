<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class UpdateKegiatanTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'update_kegiatan';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan kegiatan yang sudah ada, termasuk nama, jadwal induk, tanggal, waktu, lokasi, dan status.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Nama kegiatan yang sudah ada'],
                'nama_kegiatan' => ['type' => 'string'],
                'jadwal' => ['type' => 'string', 'description' => 'Judul jadwal induk baru'],
                'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'waktu' => ['type' => 'string', 'description' => 'HH:mm'],
                'lokasi' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['belum_dimulai', 'sedang_berjalan', 'selesai']],
            ], 'required' => ['target']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $query = Kegiatan::query()->whereHas('jadwal', fn ($builder) => $builder->where('user_id', $user->id));
        /** @var Kegiatan $activity */
        $activity = $this->resolver->resolveFromOwnedQuery($query, ['nama_kegiatan'], (string) ($arguments['target'] ?? ''), 'kegiatan');
        if (collect($arguments)->except('target')->isEmpty()) {
            throw new AiActionException('Sebutkan bagian kegiatan yang ingin diubah.');
        }

        $scheduleId = $activity->jadwal_id;
        if (array_key_exists('jadwal', $arguments)) {
            /** @var Jadwal $schedule */
            $schedule = $this->resolver->resolve($user, Jadwal::class, ['title'], (string) $arguments['jadwal'], 'jadwal');
            $scheduleId = $schedule->id;
        }
        $date = array_key_exists('tanggal', $arguments)
            ? $this->timeContext->parse($user, $arguments['tanggal'])->toDateString()
            : $activity->tanggal_deadline;
        $time = $this->timeContext->parse(
            $user,
            $date.' '.($arguments['waktu'] ?? $activity->waktu)
        )->format('H:i');

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Perbarui Kegiatan: {$activity->nama_kegiatan}",
            'payload' => [
                'kegiatan_id' => $activity->id,
                'expected_updated_at' => $activity->updated_at->format('Y-m-d H:i:s'),
                'jadwal_id' => $scheduleId,
                'nama_kegiatan' => array_key_exists('nama_kegiatan', $arguments) ? trim((string) $arguments['nama_kegiatan']) : $activity->nama_kegiatan,
                'lokasi' => array_key_exists('lokasi', $arguments) ? trim((string) $arguments['lokasi']) ?: null : $activity->lokasi,
                'tanggal_deadline' => $date,
                'waktu' => $time,
                'status' => $arguments['status'] ?? $activity->status,
            ],
        ];
    }
}
