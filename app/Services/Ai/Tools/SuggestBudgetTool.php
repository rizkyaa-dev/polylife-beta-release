<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SuggestBudgetTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'suggest_budget';
    }

    public function description(): string
    {
        return 'Menganalisis rata-rata pengeluaran beberapa bulan terakhir dan memberi saran plafon per kategori. Hanya rekomendasi; tidak menyimpan anggaran.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'lookback_months' => ['type' => 'integer', 'description' => '1-12 bulan, default 3'],
                'buffer_percent' => ['type' => 'number', 'description' => 'Buffer 0-50 persen, default 10'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $months = min(12, max(1, (int) ($arguments['lookback_months'] ?? 3)));
        $buffer = min(50, max(0, (float) ($arguments['buffer_percent'] ?? 10)));
        $end = $this->timeContext->now($user)->startOfMonth()->subDay();
        $start = $end->startOfMonth()->subMonths($months - 1);
        $rows = Keuangan::query()->where('user_id', $user->id)->where('jenis', 'pengeluaran')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('kategori, SUM(nominal) as total')->groupBy('kategori')->orderByDesc('total')->limit(20)->get();

        $suggestions = $rows->map(function ($row) use ($months, $buffer): array {
            $average = (float) $row->total / $months;
            $suggested = ceil(($average * (1 + ($buffer / 100))) / 1000) * 1000;

            return ['category' => $row->kategori, 'monthly_average' => round($average, 2), 'suggested_limit' => $suggested];
        })->all();

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'months' => $months],
            'buffer_percent' => $buffer,
            'method' => 'Rata-rata bulanan historis ditambah buffer, dibulatkan ke Rp1.000.',
            'has_data' => $rows->isNotEmpty(),
            'suggestions' => $suggestions,
        ];
    }
}
