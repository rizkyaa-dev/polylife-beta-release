<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SuggestStudyPlanTool implements AiToolInterface
{
    public function __construct(
        private readonly GetUpcomingScheduleTool $schedules,
        private readonly GetPendingTasksTool $tasks,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'suggest_study_plan';
    }

    public function description(): string
    {
        return 'Menyusun bahan rencana belajar dari deadline tugas dan jadwal 1-14 hari ke depan. Hanya rekomendasi dan tidak membuat jadwal otomatis.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'range_days' => ['type' => 'integer', 'description' => '1-14 hari, default 7'],
                'daily_minutes' => ['type' => 'integer', 'description' => '30-480 menit, default 120'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $days = min(14, max(1, (int) ($arguments['range_days'] ?? 7)));
        $minutes = min(480, max(30, (int) ($arguments['daily_minutes'] ?? 120)));
        $now = $this->timeContext->now($user);
        $work = $this->tasks->execute($user, ['limit' => 20]);
        $schedule = $this->schedules->execute($user, [
            'start_date' => $now->toDateString(),
            'end_date' => $now->addDays($days - 1)->toDateString(),
        ]);
        $priorities = collect($work['tugas'])->map(function (array $task) use ($now, $user): array {
            $deadline = ! empty($task['deadline']) ? $this->timeContext->parse($user, $task['deadline']) : null;
            $daysLeft = $deadline ? max(0, $now->startOfDay()->diffInDays($deadline->startOfDay(), false)) : null;

            return [
                'task_id' => $task['id'], 'name' => $task['nama_tugas'], 'deadline' => $task['deadline'],
                'days_left' => $daysLeft, 'suggested_session_minutes' => $daysLeft !== null && $daysLeft <= 2 ? 90 : 60,
            ];
        })->values()->all();

        return [
            'planning_window' => ['start' => $now->toDateString(), 'days' => $days, 'daily_minutes' => $minutes],
            'prioritized_tasks' => $priorities,
            'active_todos' => $work['todos'],
            'busy_schedule' => $schedule['schedules'],
            'instruction' => 'Tempatkan sesi sebelum deadline dan hindari bentrok dengan busy_schedule. Mintalah persetujuan sebelum membuat jadwal.',
        ];
    }
}
