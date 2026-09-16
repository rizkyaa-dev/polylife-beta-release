<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class UpdateJadwalTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'update_jadwal';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan jadwal yang sudah ada: judul, jenis, rentang tanggal, waktu, lokasi, semester, catatan, atau status selesai.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Judul jadwal yang sudah ada'],
                'title' => ['type' => 'string'],
                'jenis' => ['type' => 'string', 'enum' => ['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya']],
                'tanggal_mulai' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'tanggal_selesai' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'start_time' => ['type' => 'string', 'description' => 'HH:mm'],
                'end_time' => ['type' => 'string', 'description' => 'HH:mm'],
                'location' => ['type' => 'string'],
                'semester' => ['type' => 'integer'],
                'catatan_tambahan' => ['type' => 'string'],
                'is_completed' => ['type' => 'boolean'],
            ], 'required' => ['target']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Jadwal $schedule */
        $schedule = $this->resolver->resolve($user, Jadwal::class, ['title'], (string) ($arguments['target'] ?? ''), 'jadwal');
        $changes = collect($arguments)->except('target');
        if ($changes->isEmpty()) {
            throw new AiActionException('Sebutkan bagian jadwal yang ingin diubah.');
        }

        $startDate = array_key_exists('tanggal_mulai', $arguments)
            ? $this->timeContext->parse($user, $arguments['tanggal_mulai'])->toDateString()
            : $schedule->tanggal_mulai->toDateString();
        $endDate = array_key_exists('tanggal_selesai', $arguments)
            ? $this->timeContext->parse($user, $arguments['tanggal_selesai'])->toDateString()
            : $schedule->tanggal_selesai->toDateString();
        $startTime = $this->normalizeTime($user, $arguments['start_time'] ?? $schedule->start_time);
        $endTime = $this->normalizeTime($user, $arguments['end_time'] ?? $schedule->end_time);

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Perbarui Jadwal: {$schedule->title}",
            'payload' => [
                'jadwal_id' => $schedule->id,
                'expected_updated_at' => $schedule->updated_at->format('Y-m-d H:i:s'),
                'jenis' => $arguments['jenis'] ?? $schedule->jenis,
                'tanggal_mulai' => $startDate,
                'tanggal_selesai' => $endDate,
                'semester' => array_key_exists('semester', $arguments) ? (int) $arguments['semester'] : $schedule->semester,
                'catatan_tambahan' => array_key_exists('catatan_tambahan', $arguments) ? trim((string) $arguments['catatan_tambahan']) ?: null : $schedule->catatan_tambahan,
                'title' => array_key_exists('title', $arguments) ? trim((string) $arguments['title']) ?: null : $schedule->title,
                'location' => array_key_exists('location', $arguments) ? trim((string) $arguments['location']) ?: null : $schedule->location,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_completed' => array_key_exists('is_completed', $arguments) ? (bool) $arguments['is_completed'] : (bool) $schedule->is_completed,
                'matkul_ids' => $schedule->matkulIds()->map(fn ($id) => (int) $id)->all(),
            ],
        ];
    }

    private function normalizeTime(User $user, mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $this->timeContext->parse($user, $value)->format('H:i');
    }
}
