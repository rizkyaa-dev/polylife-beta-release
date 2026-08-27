<?php

namespace App\Http\Controllers;

use App\Http\Requests\Keuangan\KeuanganBudgetRequest;
use App\Models\KeuanganBudget;
use App\Services\Keuangan\BudgetEvaluationService;
use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KeuanganBudgetController extends Controller
{
    public function __construct(
        private readonly BudgetEvaluationService $budgetService,
        private readonly StatisticYearRange $statisticYearRange
    ) {
    }

    public function index(Request $request): View
    {
        $userId = (int) Auth::id();
        $now = Carbon::now();

        $month = filter_var($request->query('bulan'), FILTER_VALIDATE_INT);
        if ($month === false || $month < 1 || $month > 12) {
            $month = (int) $now->month;
        }

        $year = $this->statisticYearRange->resolve($request->query('tahun'), $now);

        $evaluation = $this->budgetService->evaluateUser($userId, $month, $year);

        $monthOptions = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return view('keuangan.anggaran', array_merge([
            'currentMonth' => $month,
            'currentYear' => $year,
            'monthOptions' => $monthOptions,
            'tahunOptions' => $this->statisticYearRange->options($now),
        ], $evaluation));
    }

    public function store(KeuanganBudgetRequest $request): RedirectResponse
    {
        $userId = (int) $request->user()->id;

        KeuanganBudget::updateOrCreate(
            [
                'user_id' => $userId,
                'kategori' => trim($request->input('kategori')),
                'bulan' => (int) $request->input('bulan'),
                'tahun' => (int) $request->input('tahun'),
            ],
            [
                'nominal_limit' => (float) $request->input('nominal_limit'),
            ]
        );

        return redirect()
            ->route('keuangan.anggaran', [
                'bulan' => $request->input('bulan'),
                'tahun' => $request->input('tahun'),
            ])
            ->with('success', 'Plafon anggaran untuk kategori ' . $request->input('kategori') . ' berhasil disimpan.');
    }

    public function destroy(KeuanganBudget $budget, Request $request): RedirectResponse
    {
        if ($budget->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $month = $budget->bulan;
        $year = $budget->tahun;
        $category = $budget->kategori;

        $budget->delete();

        return redirect()
            ->route('keuangan.anggaran', [
                'bulan' => $month,
                'tahun' => $year,
            ])
            ->with('success', 'Plafon anggaran ' . $category . ' berhasil dihapus.');
    }
}
