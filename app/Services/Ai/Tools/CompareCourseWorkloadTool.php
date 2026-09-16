<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class CompareCourseWorkloadTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'compare_course_workload';
    }

    public function description(): string
    {
        return 'Membandingkan beban mata kuliah dari SKS, jam kuliah mingguan, serta tugas tertunda. Hanya advisory.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'semester' => ['type' => 'integer', 'description' => 'Default semester terbaru'],
                'course_codes' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Kode mata kuliah tertentu, opsional'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $base = Matkul::query()->ownedBy((int) $user->id);
        $semester = isset($arguments['semester']) ? (int) $arguments['semester'] : (int) (clone $base)->max('semester');
        $courses = $base->where('semester', $semester)
            ->when(! empty($arguments['course_codes']), fn ($query) => $query->whereIn('kode', array_slice((array) $arguments['course_codes'], 0, 20)))
            ->orderBy('kode')->limit(30)->get();
        $tasks = Tugas::query()->where('user_id', $user->id)->whereIn('matkul_id', $courses->pluck('id'))->get()->groupBy('matkul_id');
        $now = $this->timeContext->now($user);

        $workloads = $courses->map(function (Matkul $course) use ($tasks, $now): array {
            $courseTasks = $tasks->get($course->id, collect());
            $pending = $courseTasks->where('status_selesai', false);
            $overdue = $pending->filter(fn (Tugas $task): bool => $task->deadline !== null && $task->deadline->lt($now))->count();
            $dueSoon = $pending->filter(fn (Tugas $task): bool => $task->deadline !== null && $task->deadline->between($now, $now->addDays(7)))->count();
            $weeklyMinutes = $course->scheduleEntries()->sum(fn (array $entry): int => $this->durationMinutes($entry['jam_mulai'] ?? null, $entry['jam_selesai'] ?? null));
            $score = ($pending->count() * 3) + ($overdue * 5) + ($dueSoon * 4) + ((int) $course->sks * 2) + (int) ceil($weeklyMinutes / 60);

            return [
                'course_id' => $course->id, 'code' => $course->kode, 'name' => $course->nama,
                'credits' => (int) $course->sks, 'weekly_class_minutes' => $weeklyMinutes,
                'pending_tasks' => $pending->count(), 'overdue_tasks' => $overdue, 'due_within_7_days' => $dueSoon,
                'relative_load_score' => $score,
            ];
        })->sortByDesc('relative_load_score')->values()->all();

        return [
            'semester' => $semester,
            'courses_compared' => count($workloads),
            'workloads' => $workloads,
            'warning' => 'Skor hanya pembanding relatif dari data workspace, bukan ukuran akademik resmi.',
        ];
    }

    private function durationMinutes(?string $start, ?string $end): int
    {
        if (! $start || ! $end || ! preg_match('/^(\d{1,2}):(\d{2})/', $start, $a) || ! preg_match('/^(\d{1,2}):(\d{2})/', $end, $b)) {
            return 0;
        }

        return max(0, (((int) $b[1] * 60) + (int) $b[2]) - (((int) $a[1] * 60) + (int) $a[2]));
    }
}
