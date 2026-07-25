<?php

namespace App\Http\Controllers;

use App\Models\Ipk;
use App\Queries\Jadwal\GuestJadwalCalendarQuery;
use App\Services\Keuangan\YearlyStatisticsService;
use App\Support\GuestWorkspace;
use App\Support\Keuangan\StatisticYearRange;
use App\ViewModels\JadwalIndexViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GuestWorkspaceController extends Controller
{
    public function __construct(
        private readonly YearlyStatisticsService $yearlyStatisticsService,
        private readonly GuestJadwalCalendarQuery $guestJadwalCalendarQuery,
        private readonly StatisticYearRange $statisticYearRange
    ) {
    }

    public function keuangan()
    {
        return view('keuangan.index', [
            'keuangans' => GuestWorkspace::keuangan(),
            'guestMode' => true,
        ]);
    }

    public function keuanganStatistik(Request $request)
    {
        $year = $this->statisticYearRange->resolve($request->query('tahun'));
        $records = GuestWorkspace::keuangan()
            ->filter(function ($row) use ($year) {
                try {
                    return Carbon::parse($row->tanggal)->year === $year;
                } catch (\Throwable $e) {
                    return false;
                }
            })
            ->map(function ($row) {
                return [
                    'jenis' => $row->jenis ?? 'pengeluaran',
                    'kategori' => $row->kategori ?? 'Lainnya',
                    'nominal' => (float) ($row->nominal ?? 0),
                    'tanggal' => Carbon::parse($row->tanggal),
                ];
            });

        $statistics = $this->yearlyStatisticsService->build($records, $year);

        return view('keuangan.statistik', array_merge([
            'tahun' => $year,
            'tahunOptions' => $this->statisticYearRange->options(),
            'guestMode' => true,
        ], $statistics));
    }

    public function jadwal(Request $request)
    {
        $selectedDate = $request->filled('tanggal')
            ? Carbon::parse($request->input('tanggal'))
            : Carbon::now();

        $calendarMonth = $request->filled('bulan')
            ? Carbon::parse($request->input('bulan') . '-01')
            : $selectedDate->copy()->startOfMonth();

        $viewModel = JadwalIndexViewModel::fromPayload(
            $this->guestJadwalCalendarQuery->forDate($selectedDate, $calendarMonth)
        );

        return view('jadwal.index', $viewModel->toArray());
    }

    public function todolist()
    {
        return view('todolist.index', [
            'todolists' => GuestWorkspace::todolists(),
            'guestMode' => true,
        ]);
    }

    public function catatan()
    {
        $catatans = GuestWorkspace::catatans()->where('status_sampah', false)->values();
        $trashCount = GuestWorkspace::catatans()->where('status_sampah', true)->count();

        return view('catatan.index', [
            'catatans' => $catatans,
            'trashCount' => $trashCount,
            'guestMode' => true,
        ]);
    }

    public function ipk()
    {
        $ipks = GuestWorkspace::ipks();
        $runningSum = 0;
        $runningCount = 0;

        $ipks = $ipks->map(function (Ipk $ipk) use (&$runningSum, &$runningCount) {
            $ipk->computed_running_ipk = null;

            if (! is_null($ipk->ips_actual)) {
                $runningSum += $ipk->ips_actual;
                $runningCount++;
                $ipk->computed_running_ipk = $runningCount ? $runningSum / $runningCount : null;
            }

            return $ipk;
        });

        $cumulativeIpk = $runningCount ? $runningSum / $runningCount : null;
        $latestIps = $ipks->whereNotNull('ips_actual')->last()?->ips_actual;

        return view('ipk.index', [
            'ipks' => $ipks,
            'cumulativeIpk' => $cumulativeIpk,
            'latestIps' => $latestIps,
            'guestMode' => true,
        ]);
    }

    public function nilaiMutu()
    {
        return view('nilai-mutu.index', [
            'nilaiMutus' => GuestWorkspace::nilaiMutus(),
            'guestMode' => true,
        ]);
    }
}
