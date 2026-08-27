<?php

namespace App\Services\Keuangan;

use App\Models\Keuangan;
use App\Models\KeuanganBudget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetEvaluationService
{
    public const DEFAULT_CATEGORIES = [
        'Makanan & Minuman',
        'Kos & Tempat Tinggal',
        'Transportasi',
        'Kebutuhan Kuliah',
        'Belanja & Pribadi',
        'Hiburan & Nongkrong',
        'Kesehatan',
        'Lainnya',
    ];

    /**
     * @return array<string, mixed>
     */
    public function evaluateUser(int $userId, int $month, int $year): array
    {
        $budgets = KeuanganBudget::where('user_id', $userId)
            ->where('bulan', $month)
            ->where('tahun', $year)
            ->orderBy('kategori')
            ->get();

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $actuals = Keuangan::where('user_id', $userId)
            ->where('jenis', 'pengeluaran')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->selectRaw('kategori, SUM(nominal) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori')
            ->map(fn ($val) => (float) $val)
            ->all();

        return $this->formatEvaluation($budgets, $actuals, $month, $year);
    }

    /**
     * @param  Collection<int, KeuanganBudget|object>  $budgets
     * @param  Collection<int, Keuangan|object|array>  $transactions
     * @return array<string, mixed>
     */
    public function evaluateGuest(Collection $budgets, Collection $transactions, int $month, int $year): array
    {
        $filteredBudgets = $budgets->filter(function ($b) use ($month, $year) {
            $bMonth = (int) (is_array($b) ? ($b['bulan'] ?? 0) : ($b->bulan ?? 0));
            $bYear = (int) (is_array($b) ? ($b['tahun'] ?? 0) : ($b->tahun ?? 0));

            return $bMonth === $month && $bYear === $year;
        })->values();

        $actuals = [];
        foreach ($transactions as $t) {
            $tJenis = is_array($t) ? ($t['jenis'] ?? '') : ($t->jenis ?? '');
            if ($tJenis !== 'pengeluaran') {
                continue;
            }

            $tDateRaw = is_array($t) ? ($t['tanggal'] ?? null) : ($t->tanggal ?? null);
            try {
                $tDate = Carbon::parse($tDateRaw);
                if ($tDate->month === $month && $tDate->year === $year) {
                    $kategori = trim((string) (is_array($t) ? ($t['kategori'] ?? 'Lainnya') : ($t->kategori ?? 'Lainnya'))) ?: 'Lainnya';
                    $nominal = (float) (is_array($t) ? ($t['nominal'] ?? 0) : ($t->nominal ?? 0));
                    $actuals[$kategori] = ($actuals[$kategori] ?? 0.0) + $nominal;
                }
            } catch (\Throwable $e) {
                // skip
            }
        }

        return $this->formatEvaluation($filteredBudgets, $actuals, $month, $year);
    }

    /**
     * @param  iterable<int, mixed>  $budgets
     * @param  array<string, float>  $actuals
     * @return array<string, mixed>
     */
    private function formatEvaluation(iterable $budgets, array $actuals, int $month, int $year): array
    {
        $items = [];
        $totalLimit = 0.0;
        $totalSpent = 0.0;
        $overbudgetCount = 0;
        $budgetedCategories = [];

        foreach ($budgets as $b) {
            $id = is_array($b) ? ($b['id'] ?? null) : ($b->id ?? null);
            $kategori = trim((string) (is_array($b) ? ($b['kategori'] ?? '') : ($b->kategori ?? '')));
            $limit = (float) (is_array($b) ? ($b['nominal_limit'] ?? 0) : ($b->nominal_limit ?? 0));
            $actual = (float) ($actuals[$kategori] ?? 0.0);

            $budgetedCategories[] = $kategori;
            $totalLimit += $limit;
            $totalSpent += $actual;

            $remaining = max(0.0, $limit - $actual);
            $overbudget = max(0.0, $actual - $limit);
            $percentage = $limit > 0 ? round(($actual / $limit) * 100, 1) : 0.0;

            if ($actual >= $limit && $limit > 0) {
                $status = 'over';
                $overbudgetCount++;
            } elseif ($percentage >= 75.0) {
                $status = 'waspada';
            } else {
                $status = 'aman';
            }

            $items[] = [
                'id' => $id,
                'kategori' => $kategori,
                'nominal_limit' => $limit,
                'actual_spent' => $actual,
                'remaining' => $remaining,
                'overbudget' => $overbudget,
                'percentage' => $percentage,
                'status' => $status,
            ];
        }

        $overallPercentage = $totalLimit > 0 ? round(($totalSpent / $totalLimit) * 100, 1) : 0.0;
        $totalRemaining = max(0.0, $totalLimit - $totalSpent);
        $totalOverbudget = max(0.0, $totalSpent - $totalLimit);

        if ($overbudgetCount > 0) {
            $healthStatus = 'Kritis / Defisit';
            $healthColor = 'rose';
        } elseif ($overallPercentage >= 80.0) {
            $healthStatus = 'Waspada';
            $healthColor = 'amber';
        } else {
            $healthStatus = 'Sehat & Terkendali';
            $healthColor = 'emerald';
        }

        // Pos pengeluaran yang ada transaksinya tapi belum memiliki plafon anggaran
        $unbudgetedExpenses = [];
        foreach ($actuals as $kat => $amount) {
            if (! in_array($kat, $budgetedCategories, true) && $amount > 0) {
                $unbudgetedExpenses[] = [
                    'kategori' => $kat,
                    'actual_spent' => $amount,
                ];
            }
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => $monthNames[$month] ?? (string) $month,
            'items' => $items,
            'unbudgeted_expenses' => $unbudgetedExpenses,
            'total_limit' => $totalLimit,
            'total_spent' => $totalSpent,
            'total_remaining' => $totalRemaining,
            'total_overbudget' => $totalOverbudget,
            'overall_percentage' => $overallPercentage,
            'overbudget_count' => $overbudgetCount,
            'health_status' => $healthStatus,
            'health_color' => $healthColor,
            'category_suggestions' => self::DEFAULT_CATEGORIES,
        ];
    }
}
