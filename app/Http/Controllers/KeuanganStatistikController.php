<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use App\Services\Keuangan\YearlyStatisticsService;
use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class KeuanganStatistikController extends Controller
{
    public function __construct(
        private readonly YearlyStatisticsService $yearlyStatisticsService,
        private readonly StatisticYearRange $statisticYearRange
    ) {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();

        $availableYears = Keuangan::where('user_id', $userId)
            ->selectRaw('DISTINCT SUBSTR(tanggal, 1, 4) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->filter(fn ($y) => $y > 0)
            ->values()
            ->all();

        if (empty($availableYears)) {
            $availableYears = [(int) Carbon::now()->year];
        }

        $year = $this->statisticYearRange->resolveFromAvailable($request->query('tahun'), $availableYears);

        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $initialBalance = (float) Keuangan::where('user_id', $userId)
            ->where('tanggal', '<', $startDate)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN nominal ELSE -nominal END), 0) as balance")
            ->value('balance');

        $records = Keuangan::where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal')
            ->get(['jenis', 'kategori', 'nominal', 'tanggal']);

        $statistics = $this->yearlyStatisticsService->build($records, $year, $initialBalance);

        return view('keuangan.statistik', array_merge([
            'tahun' => $year,
            'tahunOptions' => $availableYears,
        ], $statistics));
    }
}
