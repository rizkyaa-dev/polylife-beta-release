<?php

namespace App\Http\Controllers;

use App\Models\Ipk;
use App\Queries\Jadwal\GuestJadwalCalendarQuery;
use App\Services\Keuangan\BudgetEvaluationService;
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
        private readonly BudgetEvaluationService $budgetEvaluationService,
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

    public function keuanganAnggaran(Request $request)
    {
        $now = Carbon::now();
        $month = filter_var($request->query('bulan'), FILTER_VALIDATE_INT);
        if ($month === false || $month < 1 || $month > 12) {
            $month = (int) $now->month;
        }

        $year = $this->statisticYearRange->resolve($request->query('tahun'), $now);

        $evaluation = $this->budgetEvaluationService->evaluateGuest(
            GuestWorkspace::budgets(),
            GuestWorkspace::keuangan(),
            $month,
            $year
        );

        $monthOptions = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('keuangan.anggaran', array_merge([
            'currentMonth' => $month,
            'currentYear' => $year,
            'monthOptions' => $monthOptions,
            'tahunOptions' => $this->statisticYearRange->options($now),
            'guestMode' => true,
        ], $evaluation));
    }

    public function keuanganStatistik(Request $request)
    {
        $now = Carbon::now();
        $year = $this->statisticYearRange->resolve($request->query('tahun'), $now);
        $allGuestRecords = GuestWorkspace::keuangan();

        $initialBalance = 0.0;
        $yearRecords = collect();

        foreach ($allGuestRecords as $row) {
            try {
                $date = Carbon::parse($row->tanggal);
                if ($date->year < $year) {
                    $nominal = (float) ($row->nominal ?? 0);
                    $initialBalance += ($row->jenis === 'pemasukan' ? $nominal : -$nominal);
                } elseif ($date->year === $year) {
                    $yearRecords->push([
                        'jenis' => $row->jenis ?? 'pengeluaran',
                        'kategori' => $row->kategori ?? 'Lainnya',
                        'nominal' => (float) ($row->nominal ?? 0),
                        'tanggal' => $date,
                    ]);
                }
            } catch (\Throwable $e) {
                // skip invalid date records
            }
        }

        $statistics = $this->yearlyStatisticsService->build($yearRecords, $year, $initialBalance);

        return view('keuangan.statistik', array_merge([
            'tahun' => $year,
            'tahunOptions' => $this->statisticYearRange->options($now),
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
