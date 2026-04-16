<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use App\Services\Keuangan\YearlyStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class KeuanganStatistikController extends Controller
{
    public function __construct(
        private readonly YearlyStatisticsService $yearlyStatisticsService
    ) {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();
        $year = (int) ($request->get('tahun') ?: Carbon::now()->year);

        $records = Keuangan::where('user_id', $userId)
            ->whereYear('tanggal', $year)
            ->orderBy('tanggal')
            ->get(['jenis', 'kategori', 'nominal', 'tanggal']);

        $statistics = $this->yearlyStatisticsService->build($records, $year);

        return view('keuangan.statistik', array_merge([
            'tahun' => $year,
        ], $statistics));
    }
}
