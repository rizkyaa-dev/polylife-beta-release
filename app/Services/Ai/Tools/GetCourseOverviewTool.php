<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\Matkul;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class GetCourseOverviewTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'get_course_overview';
    }

    public function description(): string
    {
        return 'Mengambil detail satu mata kuliah beserta jadwal dan tugas terkait milik pengguna.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Kode atau nama mata kuliah'],
            ], 'required' => ['target'],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Matkul $course */
        $course = $this->resolver->resolve($user, Matkul::class, ['kode', 'nama'], (string) ($arguments['target'] ?? ''), 'mata kuliah');
        $tasks = Tugas::query()->where('user_id', $user->id)->where('matkul_id', $course->id)->orderBy('deadline')->limit(50)->get();
        $schedules = $this->courseSchedules($user, $course)->withCount('kegiatans')->limit(20)->get();

        return [
            'course' => [
                'id' => $course->id, 'code' => $course->kode, 'name' => $course->nama,
                'class' => $course->kelas, 'lecturer' => $course->dosen, 'semester' => $course->semester,
                'credits' => $course->sks, 'notes' => $course->catatan,
                'schedule_entries' => $course->scheduleEntries()->all(),
            ],
            'tasks' => $tasks->map(fn (Tugas $task): array => [
                'id' => $task->id, 'name' => $task->nama_tugas, 'deadline' => $task->deadline?->toIso8601String(),
                'completed' => (bool) $task->status_selesai,
            ])->all(),
            'task_summary' => [
                'total' => $tasks->count(),
                'pending' => $tasks->where('status_selesai', false)->count(),
                'completed' => $tasks->where('status_selesai', true)->count(),
            ],
            'schedules' => $schedules->map(fn (Jadwal $schedule): array => [
                'id' => $schedule->id, 'start_date' => $schedule->tanggal_mulai->toDateString(),
                'end_date' => $schedule->tanggal_selesai->toDateString(), 'activity_count' => $schedule->kegiatans_count,
            ])->all(),
        ];
    }

    private function courseSchedules(User $user, Matkul $course)
    {
        $id = (string) $course->id;

        return Jadwal::query()->where('user_id', $user->id)->where(function ($query) use ($id): void {
            $query->where('matkul_id_list', $id)->orWhere('matkul_id_list', 'like', $id.';%')
                ->orWhere('matkul_id_list', 'like', '%;'.$id)->orWhere('matkul_id_list', 'like', '%;'.$id.';%');
        });
    }
}
