<?php

namespace App\Http\Controllers;

use App\Queries\Dashboard\PriorityTodolistQuery;
use App\Queries\Dashboard\TodayScheduleQuery;
use App\Queries\Dashboard\UpcomingRemindersQuery;
use App\Queries\Keuangan\AvailableMonthOptionsQuery;
use App\Queries\Keuangan\MonthlyKeuanganRecordsQuery;
use App\Services\Dashboard\DashboardReminderFormatter;
use App\Services\Keuangan\MonthlyFinanceSummaryService;
use App\ViewModels\DashboardIndexViewModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardReminderFormatter $dashboardReminderFormatter,
        private readonly TodayScheduleQuery $todayScheduleQuery,
        private readonly PriorityTodolistQuery $priorityTodolistQuery,
        private readonly UpcomingRemindersQuery $upcomingRemindersQuery,
        private readonly AvailableMonthOptionsQuery $availableMonthOptionsQuery,
        private readonly MonthlyKeuanganRecordsQuery $monthlyKeuanganRecordsQuery,
        private readonly MonthlyFinanceSummaryService $monthlyFinanceSummaryService
    ) {
    }

    public function index()
    {
        $userId = Auth::id();
        $dashboardTimezone = $this->dashboardTimezone();
        $today = Carbon::today($dashboardTimezone);
        $now = Carbon::now($dashboardTimezone);
        $selectedMonth = $this->resolveMonthSelection(request()->input('bulan'), $today);

        $schedulePayload = $this->todayScheduleQuery->forUser($userId, $today);
        $recentCompletionThreshold = $now->copy()->subMinutes(10);
        $todosPrioritas = $this->priorityTodolistQuery->forUser($userId, $recentCompletionThreshold);
        $keuanganBulan = $this->monthlyKeuanganRecordsQuery->forUser($userId, $selectedMonth);
        $ringkasanKeuangan = $this->monthlyFinanceSummaryService->build($keuanganBulan, $selectedMonth);
        $remindersCollection = $this->upcomingRemindersQuery->forUser($userId, $now);
        $remindersMendatang = $this->dashboardReminderFormatter->prepare($remindersCollection, $now, $dashboardTimezone);
        $bulanOptions = $this->availableMonthOptionsQuery->forUser($userId, $selectedMonth, $today);

        $viewModel = DashboardIndexViewModel::fromPayload(array_merge($schedulePayload, [
            'todosPrioritas' => $todosPrioritas,
            'ringkasanKeuangan' => $ringkasanKeuangan,
            'remindersMendatang' => $remindersMendatang,
            'bulanOptions' => $bulanOptions,
            'bulanDipilih' => $selectedMonth->format('Y-m'),
            'todayDate' => $today,
        ]));

        return view('dashboard.index', $viewModel->toArray());
    }

    public function getKeuanganData()
    {
        $userId = Auth::id();
        $today = Carbon::today($this->dashboardTimezone());
        $selectedMonth = $this->resolveMonthSelection(request()->input('bulan'), $today);
        $keuanganBulan = $this->monthlyKeuanganRecordsQuery->forUser($userId, $selectedMonth);
        $summary = $this->monthlyFinanceSummaryService->build($keuanganBulan, $selectedMonth);

        return response()->json([
            'success' => true,
            'data' => [
                'total_pemasukan' => $summary['total_pemasukan'],
                'total_pengeluaran' => $summary['total_pengeluaran'],
                'saldo_bulan_ini' => $summary['saldo_bulan_ini'],
            ],
        ]);
    }

    public function getRemindersData()
    {
        $dashboardTimezone = $this->dashboardTimezone();
        $now = Carbon::now($dashboardTimezone);
        $reminders = $this->upcomingRemindersQuery->forUser(Auth::id(), $now);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardReminderFormatter->prepare($reminders, $now, $dashboardTimezone),
        ]);
    }

    protected function resolveMonthSelection(?string $monthParam, Carbon $fallback): Carbon
    {
        $timezone = $this->dashboardTimezone();

        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            try {
                return Carbon::createFromFormat('Y-m', $monthParam, $timezone)->startOfMonth();
            } catch (\Exception $e) {
                // fall through to fallback
            }
        }

        return $fallback->copy()->setTimezone($timezone)->startOfMonth();
    }

    protected function dashboardTimezone(): string
    {
        return config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
    }
}
