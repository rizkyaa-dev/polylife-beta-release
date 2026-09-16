<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class PrepareWeeklyReviewTool implements AiToolInterface
{
    public function __construct(
        private readonly GetUpcomingScheduleTool $schedules,
        private readonly GetPendingTasksTool $pendingTasks,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'prepare_weekly_review';
    }

    public function description(): string
    {
        return 'Menyiapkan review 7-31 hari terakhir dan agenda tujuh hari berikutnya dari data workspace. Tidak mengubah data.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'lookback_days' => ['type' => 'integer', 'description' => '7-31 hari, default 7'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $days = min(31, max(7, (int) ($arguments['lookback_days'] ?? 7)));
        $now = $this->timeContext->now($user);
        $since = $now->subDays($days - 1)->startOfDay();
        $finance = Keuangan::query()->where('user_id', $user->id)->whereBetween('tanggal', [$since->toDateString(), $now->toDateString()]);

        return [
            'period' => ['start' => $since->toDateString(), 'end' => $now->toDateString()],
            'completed' => [
                'tasks' => Tugas::query()->where('user_id', $user->id)->where('status_selesai', true)->where('updated_at', '>=', $since)->count(),
                'todos' => Todolist::query()->where('user_id', $user->id)->where('status', true)->where('updated_at', '>=', $since)->count(),
            ],
            'finance' => [
                'income' => (float) (clone $finance)->where('jenis', 'pemasukan')->sum('nominal'),
                'expense' => (float) (clone $finance)->where('jenis', 'pengeluaran')->sum('nominal'),
                'transaction_count' => (clone $finance)->count(),
            ],
            'pending_work' => $this->pendingTasks->execute($user, ['limit' => 15]),
            'next_seven_days' => $this->schedules->execute($user, [
                'start_date' => $now->toDateString(),
                'end_date' => $now->addDays(6)->toDateString(),
            ]),
            'instruction' => 'Soroti progres, hambatan, item tertunda, pola pengeluaran, dan tiga fokus realistis berikutnya.',
        ];
    }
}
