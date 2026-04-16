<?php

namespace App\Http\Controllers;

use App\Services\Jadwal\KegiatanCalendarService;
use App\Services\Jadwal\KuliahScheduleService;
use App\Services\Keuangan\MonthlyFinanceSummaryService;
use App\Services\Reminder\GuestReminderFeedService;
use App\Support\GuestWorkspace;
use App\ViewModels\DashboardIndexViewModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class GuestDashboardController extends Controller
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService,
        private readonly KegiatanCalendarService $kegiatanCalendarService,
        private readonly MonthlyFinanceSummaryService $monthlyFinanceSummaryService,
        private readonly GuestReminderFeedService $guestReminderFeedService
    ) {
    }

    public function index()
    {
        $timezone = config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
        $today = Carbon::today($timezone);
        $now = Carbon::now($timezone);
        $matkuls = GuestWorkspace::matkuls();

        $jadwalHariIni = GuestWorkspace::jadwals()
            ->filter(function ($jadwal) use ($today) {
                $start = Carbon::parse($jadwal->tanggal_mulai);
                $end = Carbon::parse($jadwal->tanggal_selesai);

                return $today->betweenIncluded($start, $end);
            })
            ->values();

        $this->kuliahScheduleService->appendMatkulDetails($jadwalHariIni, $matkuls);
        $jadwalHariIni = $this->kuliahScheduleService->deduplicateKuliahCollection($jadwalHariIni);

        $viewModel = DashboardIndexViewModel::fromPayload([
            'guestMode' => true,
            'todayDate' => $today,
            'jadwalHariIni' => $jadwalHariIni,
            'matkuls' => $matkuls,
            'todosPrioritas' => GuestWorkspace::todolists()
                ->sortBy(fn ($todo) => [$todo->status ? 1 : 0, $todo->updated_at ?? Carbon::now()->subDay()])
                ->take(6),
            'ringkasanKeuangan' => $this->monthlyFinanceSummaryService->build(GuestWorkspace::keuangan(), $today),
            'remindersMendatang' => $this->guestReminderFeedService->build($now, 6),
            'quickStats' => $this->quickStatsFallback(),
            'bulanOptions' => [[
                'value' => $today->format('Y-m'),
                'label' => $today->translatedFormat('F Y'),
            ]],
            'bulanDipilih' => $today->format('Y-m'),
            'kegiatanByJadwal' => $this->kegiatanCalendarService->collectByJadwalForDate($jadwalHariIni, $today),
        ]);

        return view('dashboard.index', $viewModel->toArray());
    }

    private function quickStatsFallback(): array
    {
        $path = storage_path('app/guest/dashboard.json');
        $payload = [];

        if (File::exists($path)) {
            $decoded = json_decode(File::get($path), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $defaults = [
            'stats' => [
                'ipk' => '3.50',
                'saldo' => 'Rp1.250.000',
                'tugas' => ['aktif' => 3, 'selesai' => 1],
            ],
        ];

        $data = array_replace_recursive($defaults, $payload);

        return $data['stats'];
    }
}
