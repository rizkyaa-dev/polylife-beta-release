<?php

namespace App\Http\Controllers;

use App\Support\GuestWorkspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GuestDashboardController extends Controller
{
    public function index()
    {
        $timezone = config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
        $today = Carbon::today($timezone);
        $matkuls = GuestWorkspace::matkuls();

        $jadwalHariIni = $this->jadwalUntukTanggal($today);
        $todosPrioritas = GuestWorkspace::todolists()
            ->sortBy(fn ($todo) => [$todo->status ? 1 : 0, $todo->updated_at ?? Carbon::now()->subDay()])
            ->take(6);
        $ringkasanKeuangan = $this->ringkasanKeuanganBulan($today);
        $remindersMendatang = $this->remindersGuest($today);
        $quickStats = $this->quickStatsFallback();

        $bulanOptions = collect([[
            'value' => $today->format('Y-m'),
            'label' => $today->translatedFormat('F Y'),
        ]]);

        return view('dashboard.index', [
            'guestMode' => true,
            'todayDate' => $today,
            'jadwalHariIni' => $jadwalHariIni,
            'matkuls' => $matkuls,
            'todosPrioritas' => $todosPrioritas,
            'ringkasanKeuangan' => $ringkasanKeuangan,
            'remindersMendatang' => $remindersMendatang,
            'quickStats' => $quickStats,
            'bulanOptions' => $bulanOptions,
            'bulanDipilih' => $today->format('Y-m'),
            'kegiatanByJadwal' => collect(),
        ]);
    }

    private function jadwalUntukTanggal(Carbon $date): Collection
    {
        $jadwals = GuestWorkspace::jadwals()
            ->filter(function ($jadwal) use ($date) {
                $start = Carbon::parse($jadwal->tanggal_mulai);
                $end = Carbon::parse($jadwal->tanggal_selesai);
                return $date->betweenIncluded($start, $end);
            })
            ->values();

        return $this->deduplicateKuliahCollection($jadwals);
    }

    private function ringkasanKeuanganBulan(Carbon $reference): array
    {
        $startMonth = $reference->copy()->startOfMonth();
        $endMonth = $reference->copy()->endOfMonth();

        $keuanganBulan = GuestWorkspace::keuangan()
            ->filter(function ($row) use ($startMonth, $endMonth) {
                try {
                    $tanggal = Carbon::parse($row->tanggal);
                } catch (\Throwable $e) {
                    return false;
                }

                return $tanggal->betweenIncluded($startMonth, $endMonth);
            });

        $totalPemasukan = $keuanganBulan->where('jenis', 'pemasukan')->sum('nominal');
        $totalPengeluaran = $keuanganBulan->where('jenis', 'pengeluaran')->sum('nominal');
        $saldoBulanIni = $totalPemasukan - $totalPengeluaran;

        return [
            'total_pemasukan' => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'saldo_bulan_ini' => $saldoBulanIni,
            'bulan_label' => $reference->translatedFormat('F Y'),
        ];
    }

    private function remindersGuest(Carbon $now): array
    {
        $reminders = GuestWorkspace::todolists()
            ->flatMap(fn ($todo) => $todo->reminders->map(function ($rem) use ($todo) {
                $rem->todo_title = $todo->nama_item;
                return $rem;
            }))
            ->filter(fn ($rem) => $rem->aktif && $rem->waktu_reminder && Carbon::parse($rem->waktu_reminder)->gte($now))
            ->sortBy('waktu_reminder')
            ->take(6);

        return $reminders->map(function ($reminder) use ($now) {
            $deadline = Carbon::parse($reminder->waktu_reminder);
            $secondsLeft = max(0, $now->diffInSeconds($deadline, false));
            return [
                'id' => $reminder->id,
                'title' => $reminder->todo_title ?? 'Reminder',
                'waktu_formatted' => $deadline->translatedFormat('l, d F Y H:i'),
                'time_left_text' => $this->formatTimeLeft($secondsLeft),
                'time_diff' => $deadline->diffForHumans($now, false, false, 2),
                'seconds_left' => $secondsLeft,
                'badge_classes' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'dot_classes' => 'bg-indigo-400',
                'blink' => false,
                'edit_url' => '#',
            ];
        })->values()->all();
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

    private function formatTimeLeft(int $secondsLeft): string
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

        $remaining = $secondsLeft;
        $parts = [];

        foreach ($unitsSeconds as $unit => $sec) {
            $value = intdiv($remaining, $sec);
            if ($value <= 0 && !empty($parts)) {
                continue;
            }
            if ($value > 0) {
                $parts[] = $value . ' ' . $unit;
                $remaining -= $value * $sec;
            }
            if (count($parts) >= 2) {
                break;
            }
        }

        if (empty($parts)) {
            $parts[] = '0 detik';
        }

        return 'Sisa ' . implode(' ', $parts);
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

    private function kuliahSignature($jadwal): string
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
}
