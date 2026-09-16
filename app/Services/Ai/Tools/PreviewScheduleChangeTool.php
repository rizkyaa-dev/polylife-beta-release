<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\ScheduleAvailabilityService;
use App\Services\Ai\UserTimeContext;
use Illuminate\Validation\ValidationException;

final class PreviewScheduleChangeTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly ScheduleAvailabilityService $availability,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'preview_schedule_change';
    }

    public function description(): string
    {
        return 'Mempreview dampak perubahan tanggal/waktu jadwal, termasuk bentrok dan jumlah relasi terdampak, tanpa menyimpan perubahan.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Judul jadwal'],
                'start_date' => ['type' => 'string', 'description' => 'Tanggal mulai baru YYYY-MM-DD'],
                'end_date' => ['type' => 'string', 'description' => 'Tanggal selesai baru YYYY-MM-DD'],
                'start_time' => ['type' => 'string', 'description' => 'Waktu mulai baru HH:MM'],
                'end_time' => ['type' => 'string', 'description' => 'Waktu selesai baru HH:MM'],
            ], 'required' => ['target'],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Jadwal $schedule */
        $schedule = $this->resolver->resolve($user, Jadwal::class, ['title'], (string) ($arguments['target'] ?? ''), 'jadwal');
        $startDate = (string) ($arguments['start_date'] ?? $schedule->tanggal_mulai->toDateString());
        $endDate = (string) ($arguments['end_date'] ?? $schedule->tanggal_selesai->toDateString());
        $startTime = (string) ($arguments['start_time'] ?? $schedule->start_time ?? '00:00');
        $endTime = (string) ($arguments['end_time'] ?? $schedule->end_time ?? '23:59');
        $start = $this->timeContext->parse($user, $startDate.' '.$startTime);
        $end = $this->timeContext->parse($user, $endDate.' '.$endTime);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_time' => 'Waktu selesai baru harus setelah waktu mulai baru.']);
        }
        $conflicts = $this->availability->conflicts($user, $start, $end, $schedule->id);

        return [
            'schedule_id' => $schedule->id,
            'title' => $schedule->title,
            'current' => [
                'start_date' => $schedule->tanggal_mulai->toDateString(), 'end_date' => $schedule->tanggal_selesai->toDateString(),
                'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time, 'location' => $schedule->location,
            ],
            'proposed' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'has_conflict' => $conflicts !== [],
            'conflicts' => $conflicts,
            'affected_relations' => [
                'activities' => $schedule->kegiatans()->count(),
                'reminders' => $schedule->reminders()->count(),
            ],
            'warning' => 'Preview tidak menyimpan perubahan. Kegiatan dan reminder terkait perlu ditinjau sebelum konfirmasi update.',
        ];
    }
}
