<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use App\Models\Jadwal;
use App\Models\Matkul;
use App\Models\Todolist;
use App\Models\Reminder;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $dashboardTimezone = $this->dashboardTimezone();
        $today = Carbon::today($dashboardTimezone);
        $now = Carbon::now($dashboardTimezone);
        $selectedMonthParam = request()->input('bulan');
        $selectedMonth = $this->resolveMonthSelection($selectedMonthParam, $today);
        $startMonth = $selectedMonth->copy()->startOfMonth();
        $endMonth = $selectedMonth->copy()->endOfMonth();

        $jadwalHariIni = Jadwal::with('kegiatans')
            ->where('user_id', $userId)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->orderBy('tanggal_mulai')
            ->get();
        $matkuls = Matkul::where('user_id', $userId)
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();
        $this->appendMatkulDetails($jadwalHariIni, $userId, $matkuls);
        $jadwalHariIni = $this->deduplicateKuliahCollection($jadwalHariIni);
        $kegiatanByJadwal = $this->collectKegiatanByJadwalForDate($jadwalHariIni, $today);

        $recentCompletionThreshold = $now->copy()->subMinutes(10);

        $todosPrioritas = Todolist::where('user_id', $userId)
            ->where(function ($query) use ($recentCompletionThreshold) {
                $query->where('status', false)
                    ->orWhere(function ($sub) use ($recentCompletionThreshold) {
                        $sub->where('status', true)
                            ->where('updated_at', '>=', $recentCompletionThreshold);
                    });
            })
            ->orderBy('status')
            ->orderByDesc('updated_at')
            ->take(8)
            ->get();

        $todosPrioritas->each(function ($todo) use ($recentCompletionThreshold) {
            $todo->recently_completed = $todo->status && $todo->updated_at >= $recentCompletionThreshold;
        });

        $keuanganBulan = Keuangan::where('user_id', $userId)
            ->whereBetween('tanggal', [$startMonth, $endMonth])
            ->get();

        $total_pemasukan = $keuanganBulan->where('jenis', 'pemasukan')->sum('nominal');
        $total_pengeluaran = $keuanganBulan->where('jenis', 'pengeluaran')->sum('nominal');
        $saldo_bulan_ini = $total_pemasukan - $total_pengeluaran;

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];
        foreach (CarbonPeriod::create($startMonth, $endMonth) as $date) {
            $labels[] = $date->format('d M');
            $seriesPemasukan[] = $keuanganBulan
                ->where('jenis', 'pemasukan')
                ->where('tanggal', $date->toDateString())
                ->sum('nominal');
            $seriesPengeluaran[] = $keuanganBulan
                ->where('jenis', 'pengeluaran')
                ->where('tanggal', $date->toDateString())
                ->sum('nominal');
        }

        $ringkasanKeuangan = [
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo_bulan_ini' => $saldo_bulan_ini,
            'dataset_grafik' => [
                'labels' => $labels,
                'pemasukan' => $seriesPemasukan,
                'pengeluaran' => $seriesPengeluaran,
            ],
        ];

        $remindersCollection = Reminder::with(['todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->where('user_id', $userId)
            ->where('aktif', true)
            ->where('waktu_reminder', '>=', $now)
            ->orderBy('waktu_reminder')
            ->take(8)
            ->get();
        $remindersMendatang = $this->prepareRemindersForDashboard($remindersCollection, $now, $dashboardTimezone);

        $bulanOptions = $this->buildMonthOptions($userId, $selectedMonth, $today);
        $bulanDipilih = $selectedMonth->format('Y-m');

        return view('dashboard.index', [
            'jadwalHariIni' => $jadwalHariIni,
            'todosPrioritas' => $todosPrioritas,
            'ringkasanKeuangan' => $ringkasanKeuangan,
            'remindersMendatang' => $remindersMendatang,
            'bulanOptions' => $bulanOptions,
            'bulanDipilih' => $bulanDipilih,
            'kegiatanByJadwal' => $kegiatanByJadwal,
            'todayDate' => $today,
            'matkuls' => $matkuls,
        ]);
    }

    /**
     * Get financial data for automatic chart update (AJAX)
     */
    public function getKeuanganData()
    {
        $userId = Auth::id();
        $dashboardTimezone = $this->dashboardTimezone();
        $today = Carbon::today($dashboardTimezone);
        $selectedMonth = $this->resolveMonthSelection(request()->input('bulan'), $today);
        $startMonth = $selectedMonth->copy()->startOfMonth();
        $endMonth = $selectedMonth->copy()->endOfMonth();

        // Ambil data keuangan bulan ini
        $keuanganBulan = Keuangan::where('user_id', $userId)
            ->whereBetween('tanggal', [$startMonth, $endMonth])
            ->get();

        $total_pemasukan = $keuanganBulan->where('jenis', 'pemasukan')->sum('nominal');
        $total_pengeluaran = $keuanganBulan->where('jenis', 'pengeluaran')->sum('nominal');
        $saldo_bulan_ini = $total_pemasukan - $total_pengeluaran;

        return response()->json([
            'success' => true,
            'data' => [
                'total_pemasukan' => $total_pemasukan,
                'total_pengeluaran' => $total_pengeluaran,
                'saldo_bulan_ini' => $saldo_bulan_ini,
            ]
        ]);
    }

    /**
     * Get active reminders for dashboard card (AJAX)
     */
    public function getRemindersData()
    {
        $userId = Auth::id();
        $dashboardTimezone = $this->dashboardTimezone();
        $now = Carbon::now($dashboardTimezone);

        $reminders = Reminder::with(['todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->where('user_id', $userId)
            ->where('aktif', true)
            ->where('waktu_reminder', '>=', $now)
            ->orderBy('waktu_reminder')
            ->take(8)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $this->prepareRemindersForDashboard($reminders, $now, $dashboardTimezone),
        ]);
    }

    /**
     * Determine which month should be used based on query parameter.
     */
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

    /**
     * Build the dropdown options for available months.
     */
    protected function buildMonthOptions(int $userId, Carbon $selectedMonth, Carbon $today)
    {
        $rawOptions = Keuangan::where('user_id', $userId)
            ->selectRaw('DATE_FORMAT(tanggal, "%Y-%m-01") as periode')
            ->distinct()
            ->orderByDesc('periode')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                $date = Carbon::parse($row->periode);
                return [
                    'value' => $date->format('Y-m'),
                    'label' => $date->translatedFormat('F Y'),
                ];
            });

        if ($rawOptions->isEmpty()) {
            $rawOptions->push([
                'value' => $today->format('Y-m'),
                'label' => $today->translatedFormat('F Y'),
            ]);
        }

        if (!$rawOptions->contains(fn ($opt) => $opt['value'] === $selectedMonth->format('Y-m'))) {
            $rawOptions->push([
                'value' => $selectedMonth->format('Y-m'),
                'label' => $selectedMonth->translatedFormat('F Y'),
            ]);
        }

        return $rawOptions
            ->unique('value')
            ->sortByDesc('value')
            ->values();
    }

    private function deduplicateKuliahCollection(Collection $jadwals): Collection
    {
        $seen = [];

        return $jadwals->filter(function ($jadwal) use (&$seen) {
            if (($jadwal->jenis ?? null) !== 'kuliah') {
                return true;
            }

            $signature = $this->kuliahSignature($jadwal);
            if (isset($seen[$signature])) {
                return false;
            }

            $seen[$signature] = true;
            return true;
        })->values();
    }

    private function kuliahSignature(Jadwal $jadwal): string
    {
        $signatures = collect();

        $matkulIds = $jadwal->matkulIds()
            ->map(fn ($id) => 'id:' . (string) $id)
            ->filter();
        if ($matkulIds->isNotEmpty()) {
            $signatures = $signatures->merge($matkulIds);
        }

        $matkulNames = collect($jadwal->matkul_names ?? [])
            ->map(fn ($name) => 'name:' . Str::lower(trim((string) $name)))
            ->filter();
        if ($matkulNames->isNotEmpty()) {
            $signatures = $signatures->merge($matkulNames);
        }

        $matkulDetails = collect($jadwal->matkul_details ?? [])
            ->map(fn ($matkul) => $this->matkulDetailSignature($matkul))
            ->filter();
        if ($matkulDetails->isNotEmpty()) {
            $signatures = $signatures->merge($matkulDetails);
        }

        if ($signatures->isEmpty()) {
            $signatures = $jadwal->matkulIds()
                ->merge($matkulDetails)
                ->merge($matkulNames)
                ->filter();
        }

        $signature = $signatures
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(';');
        if ($signature === '') {
            $signature = 'generic';
        }

        return 'kuliah|' . $signature;
    }

    private function matkulDetailSignature($matkul): string
    {
        $name = Str::lower(trim((string) ($matkul->nama ?? '')));
        $kode = Str::lower(trim((string) ($matkul->kode ?? '')));
        $id = isset($matkul->id) ? (string) $matkul->id : '';
        $start = $this->resolveMatkulTime($matkul, 'primaryStartTime', 'jam_mulai');
        $end = $this->resolveMatkulTime($matkul, 'primaryEndTime', 'jam_selesai');

        if ($id === '' && $name === '' && $kode === '' && $start === '' && $end === '') {
            return '';
        }

        return implode('|', array_filter([
            $id !== '' ? 'id:' . $id : null,
            $name !== '' ? 'name:' . $name : null,
            $kode !== '' ? 'kode:' . $kode : null,
            $start !== '' ? 'start:' . $start : null,
            $end !== '' ? 'end:' . $end : null,
        ]));
    }

    private function resolveMatkulTime($matkul, string $method, string $property): string
    {
        if (method_exists($matkul, $method)) {
            $value = $matkul->{$method}();
        } elseif (isset($matkul->{$property})) {
            $value = $matkul->{$property};
        } else {
            $value = null;
        }

        return $value ? (string) $value : '';
    }

    /**
     * Format reminder data for dashboard display.
     */
    protected function prepareRemindersForDashboard($reminders, Carbon $now, string $timezone)
    {
        return $reminders
            ->map(function ($reminder) use ($now, $timezone) {
                return $this->formatReminderData($reminder, $now, $timezone);
            })
            ->filter()
            ->values();
    }

    protected function resolveReminderTitle(Reminder $reminder): string
    {
        if ($reminder->todolist_id) {
            return optional($reminder->todolist)->nama_item ?: 'Todolist #' . $reminder->todolist_id;
        }

        if ($reminder->tugas_id) {
            return optional($reminder->tugas)->nama_tugas ?: 'Tugas #' . $reminder->tugas_id;
        }

        if ($reminder->kegiatan_id) {
            return optional($reminder->kegiatan)->nama_kegiatan ?: 'Kegiatan #' . $reminder->kegiatan_id;
        }

        if ($reminder->jadwal_id) {
            $jadwal = $reminder->jadwal;
            if ($jadwal) {
                return $jadwal->catatan_tambahan
                    ?: $jadwal->jenis
                    ?: 'Jadwal #' . $jadwal->id;
            }

            return 'Jadwal #' . $reminder->jadwal_id;
        }

        return optional($reminder->todolist)->nama_item
            ?? optional($reminder->tugas)->nama_tugas
            ?? optional($reminder->kegiatan)->nama_kegiatan
            ?? optional($reminder->jadwal)->jenis
            ?? 'Reminder';
    }

    protected function formatReminderData(Reminder $reminder, Carbon $now, string $timezone)
    {
        $rawDatetime = $reminder->getRawOriginal('waktu_reminder');
        if (! $rawDatetime) {
            return null;
        }

        try {
            $deadline = Carbon::createFromFormat('Y-m-d H:i:s', $rawDatetime, $timezone);
        } catch (\Exception $e) {
            return null;
        }

        $nowTz = $now->copy()->setTimezone($timezone);

        if ($deadline->lt($nowTz)) {
            return null;
        }

        $secondsLeft = max(0, $nowTz->diffInSeconds($deadline, false));
        [$badgeClasses, $dotClasses, $blink] = $this->reminderUrgencyClasses($secondsLeft);
        $locale = 'id';
        Carbon::setLocale($locale);
        $deadlineLocalized = $deadline->copy()->locale($locale);

        return [
            'id' => $reminder->id,
            'title' => $this->resolveReminderTitle($reminder),
            'waktu_iso' => $deadline->toIso8601String(),
            'waktu_formatted' => $deadlineLocalized->translatedFormat('l, d F Y H:i'),
            'time_diff' => $deadlineLocalized->diffForHumans($nowTz, false, false, 2),
            'time_left_text' => $this->formatTimeLeft($secondsLeft),
            'seconds_left' => $secondsLeft,
            'badge_classes' => $badgeClasses,
            'dot_classes' => $dotClasses,
            'blink' => $blink,
            'edit_url' => route('reminder.edit', $reminder->id),
        ];
    }

    protected function formatTimeLeft(int $secondsLeft): string
    {
        if ($secondsLeft <= 0) {
            return 'Segera jatuh tempo';
        }
        $unitsSeconds = [
            'bulan' => 30 * 24 * 3600,
            'minggu' => 7 * 24 * 3600,
            'hari' => 24 * 3600,
            'jam' => 3600,
            'menit' => 60,
            'detik' => 1,
        ];

        $unitOrder = $this->timeLeftUnitSet($secondsLeft);
        $remaining = $secondsLeft;
        $parts = [];

        foreach ($unitOrder as $index => $unit) {
            $sec = $unitsSeconds[$unit];
            $value = intdiv($remaining, $sec);
            if ($value === 0 && $index === 0) {
                continue;
            }
            $parts[] = $value . ' ' . $unit;
            $remaining -= $value * $sec;
        }

        if (empty($parts)) {
            $parts[] = '0 ' . end($unitOrder);
        }

        return 'Sisa ' . implode(' ', $parts);
    }

    protected function timeLeftUnitSet(int $secondsLeft): array
    {
        $month = 30 * 24 * 3600;
        $week = 7 * 24 * 3600;
        $day = 24 * 3600;
        $hour = 3600;

        if ($secondsLeft >= $month) {
            return ['bulan', 'minggu', 'hari'];
        }

        if ($secondsLeft >= $week) {
            return ['minggu', 'hari', 'jam'];
        }

        if ($secondsLeft >= $day) {
            return ['hari', 'jam', 'menit'];
        }

        return ['jam', 'menit', 'detik'];
    }

    protected function reminderUrgencyClasses(int $secondsLeft): array
    {
        $oneDay = 24 * 3600;
        $oneWeek = 7 * $oneDay;
        $threeHours = 3 * 3600;

        if ($secondsLeft >= $oneWeek) {
            return [
                'bg-green-50 text-green-700 border border-green-100',
                'bg-green-400',
                false,
            ];
        }

        if ($secondsLeft >= $oneDay) {
            return [
                'bg-amber-50 text-amber-700 border border-amber-200',
                'bg-amber-400',
                false,
            ];
        }

        if ($secondsLeft >= $threeHours) {
            return [
                'bg-rose-50 text-rose-700 border border-rose-200',
                'bg-rose-400',
                false,
            ];
        }

        return [
            'bg-rose-700 text-white border border-black dark:border-white',
            'reminder-dot-critical',
            true,
        ];
    }

    protected function dashboardTimezone(): string
    {
        return config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
    }

    private function appendMatkulDetails($jadwals, int $userId, ?Collection $matkuls = null): Collection
    {
        $matkulCollection = $matkuls ?: Matkul::where('user_id', $userId)->get();
        $matkulMap = $matkulCollection->keyBy('id');
        foreach ($jadwals as $jadwal) {
            $ids = $jadwal->matkulIds();
            $details = $ids->map(fn ($id) => $matkulMap->get((int) $id))
                ->filter()
                ->values();
            $jadwal->matkul_details = $details;
        }

        return $matkulCollection;
    }

    private function collectKegiatanByJadwalForDate($jadwals, Carbon $date)
    {
        $map = collect();

        foreach ($jadwals as $jadwal) {
            $filtered = $jadwal->kegiatans->filter(function ($kegiatan) use ($date) {
                $tanggal = $this->resolveKegiatanDate($kegiatan);
                return $tanggal === $date->toDateString();
            });

            if ($filtered->isNotEmpty()) {
                $map[$jadwal->id] = $filtered->values();
            }
        }

        return $map;
    }

    private function resolveKegiatanDate($kegiatan): ?string
    {
        if (!empty($kegiatan->tanggal_deadline)) {
            return Carbon::parse($kegiatan->tanggal_deadline)->toDateString();
        }

        if (!empty($kegiatan->waktu)) {
            return Carbon::parse($kegiatan->waktu)->toDateString();
        }

        return null;
    }
}
