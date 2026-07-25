<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use App\Services\Keuangan\YearlyStatisticsService;
use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Http\Request;
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
        $year = $this->statisticYearRange->resolve($request->query('tahun'));

        $records = Keuangan::where('user_id', $userId)
            ->whereYear('tanggal', $year)
            ->orderBy('tanggal')
            ->get(['jenis', 'kategori', 'nominal', 'tanggal']);

        $statistics = $this->yearlyStatisticsService->build($records, $year);

        return view('keuangan.statistik', array_merge([
            'tahun' => $year,
            'tahunOptions' => $this->statisticYearRange->options(),
        ], $statistics));
    }
}
