<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;
use App\Services\Keuangan\BudgetEvaluationService;

class GetFinancialSummaryTool implements AiToolInterface
{
    public function __construct(
        private readonly BudgetEvaluationService $budgetEvaluation,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'get_financial_summary';
    }

    public function description(): string
    {
        return 'Mengambil ringkasan keuangan (total pemasukan, pengeluaran, saldo, dan status anggaran) milik pengguna pada bulan tertentu.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'month' => [
                        'type' => 'string',
                        'description' => 'Bulan yang dicari format YYYY-MM (default bulan berjalan)',
                    ],
                ],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $month = $arguments['month'] ?? $this->timeContext->now($user)->format('Y-m');
        if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return [
                'status' => 'invalid_arguments',
                'message' => 'Bulan harus memakai format YYYY-MM dan berisi bulan yang valid.',
            ];
        }

        $targetMonth = $this->timeContext->parse($user, $month.'-01')->startOfMonth();
        $startOfMonth = $targetMonth->copy()->startOfMonth()->toDateString();
        $endOfMonth = $targetMonth->copy()->endOfMonth()->toDateString();

        $query = Keuangan::query()
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);

        $totalPemasukan = (float) (clone $query)->where('jenis', 'pemasukan')->sum('nominal');
        $totalPengeluaran = (float) (clone $query)->where('jenis', 'pengeluaran')->sum('nominal');
        $saldoBulanIni = $totalPemasukan - $totalPengeluaran;

        $budgetEvaluation = $this->budgetEvaluation->evaluateUser(
            (int) $user->id,
            (int) $targetMonth->month,
            (int) $targetMonth->year
        );
        $hasBudget = ! empty($budgetEvaluation['items']);
        $budgetItems = collect($budgetEvaluation['items']);

        $topKategori = (clone $query)
            ->where('jenis', 'pengeluaran')
            ->selectRaw('kategori, SUM(nominal) as total')
            ->groupBy('kategori')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'kategori')
            ->toArray();

        return [
            'month' => $targetMonth->format('Y-m'),
            'total_pemasukan' => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'saldo_bulan_ini' => $saldoBulanIni,
            'budget_limit' => $hasBudget ? (float) $budgetEvaluation['total_limit'] : null,
            'budget_remaining' => $hasBudget ? (float) $budgetItems->sum('remaining') : null,
            'budget_overage' => $hasBudget ? (float) $budgetItems->sum('overbudget') : null,
            'budget_status' => $hasBudget ? (string) $budgetEvaluation['health_status'] : null,
            'top_pengeluaran_kategori' => $topKategori,
        ];
    }
}
