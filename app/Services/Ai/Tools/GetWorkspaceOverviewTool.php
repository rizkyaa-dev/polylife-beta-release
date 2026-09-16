<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class GetWorkspaceOverviewTool implements AiToolInterface
{
    public function __construct(
        private readonly GetUpcomingScheduleTool $schedules,
        private readonly GetPendingTasksTool $tasks,
        private readonly GetFinancialSummaryTool $finances,
        private readonly GetRemindersTool $reminders,
        private readonly GetAcademicSummaryTool $academic,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'get_workspace_overview';
    }

    public function description(): string
    {
        return 'Mengambil snapshot lintas-domain yang ringkas: jadwal, tugas/to-do, reminder, keuangan bulan ini, dan akademik. Gunakan untuk prioritas atau briefing umum.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'range_days' => ['type' => 'integer', 'description' => 'Rentang jadwal 1-31 hari, default 7'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $days = min(31, max(1, (int) ($arguments['range_days'] ?? 7)));
        $now = $this->timeContext->now($user);

        return [
            'generated_at' => $now->toIso8601String(),
            'range_days' => $days,
            'schedule' => $this->schedules->execute($user, [
                'start_date' => $now->toDateString(),
                'end_date' => $now->addDays($days - 1)->toDateString(),
            ]),
            'pending_work' => $this->tasks->execute($user, ['limit' => 10]),
            'reminders' => $this->reminders->execute($user, ['limit' => 10]),
            'finance' => $this->finances->execute($user, ['month' => $now->format('Y-m')]),
            'academic' => $this->academic->execute($user, []),
        ];
    }
}
