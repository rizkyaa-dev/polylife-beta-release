<?php

namespace App\Http\Controllers;

use App\Models\Ipk;
use App\Models\Jadwal;
use App\Support\GuestWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GuestWorkspaceController extends Controller
{
    public function keuangan()
    {
        return view('keuangan.index', [
            'keuangans' => GuestWorkspace::keuangan(),
            'guestMode' => true,
        ]);
    }

    public function keuanganStatistik(Request $request)
    {
        $year = (int) ($request->get('tahun') ?: Carbon::now()->year);
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

        $months = range(1, 12);
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $byMonth = [];
        foreach ($months as $m) {
            $byMonth[$m] = [
                'pemasukan' => 0,
                'pengeluaran' => 0,
                'net' => 0,
            ];
        }

        $kategoriPengeluaran = [];
        $kategoriPemasukan = [];

        foreach ($records as $r) {
            $m = (int) $r['tanggal']->month;
            if (($r['jenis'] ?? '') === 'pemasukan') {
                $byMonth[$m]['pemasukan'] += (float) $r['nominal'];
                $kategoriPemasukan[$r['kategori'] ?: 'Lainnya'] = ($kategoriPemasukan[$r['kategori'] ?: 'Lainnya'] ?? 0) + (float) $r['nominal'];
            } else {
                $byMonth[$m]['pengeluaran'] += (float) $r['nominal'];
                $kategoriPengeluaran[$r['kategori'] ?: 'Lainnya'] = ($kategoriPengeluaran[$r['kategori'] ?: 'Lainnya'] ?? 0) + (float) $r['nominal'];
            }
        }

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];
        $seriesNet = [];
        $cumulativeSaldo = [];

        $running = 0;
        foreach ($months as $m) {
            $labels[] = $monthNames[$m];
            $pm = $byMonth[$m]['pemasukan'];
            $pg = $byMonth[$m]['pengeluaran'];
            $net = $pm - $pg;
            $seriesPemasukan[] = $pm;
            $seriesPengeluaran[] = $pg;
            $seriesNet[] = $net;
            $running += $net;
            $cumulativeSaldo[] = $running;
            $byMonth[$m]['net'] = $net;
        }

        $totalPemasukan = array_sum($seriesPemasukan);
        $totalPengeluaran = array_sum($seriesPengeluaran);
        $totalNet = $totalPemasukan - $totalPengeluaran;

        $bulanTerisi = max(1, (int) min(12, Carbon::now()->year === $year ? Carbon::now()->month : 12));
        $avgPemasukan = $bulanTerisi ? $totalPemasukan / $bulanTerisi : 0;
        $avgPengeluaran = $bulanTerisi ? $totalPengeluaran / $bulanTerisi : 0;
        $avgNet = $bulanTerisi ? $totalNet / $bulanTerisi : 0;

        $meanPg = $avgPengeluaran;
        $variance = 0;
        foreach ($months as $m) {
            $variance += pow(($byMonth[$m]['pengeluaran'] - $meanPg), 2);
        }
        $stdPg = sqrt($variance / count($months));
        $anomali = [];
        $threshold = $meanPg + (1.5 * $stdPg);
        foreach ($months as $m) {
            if ($byMonth[$m]['pengeluaran'] > $threshold && $byMonth[$m]['pengeluaran'] > 0) {
                $anomali[] = [
                    'bulan' => $monthNames[$m],
                    'nilai' => $byMonth[$m]['pengeluaran'],
                    'batas' => $threshold,
                ];
            }
        }

        $savingsRate = $totalPemasukan > 0 ? ($totalNet / $totalPemasukan) : 0;
        $burnRate = $avgPengeluaran;
        $sisaBulan = max(0, 12 - $bulanTerisi);
        $proyeksiAkhirTahun = $totalNet + ($avgNet * $sisaBulan);

        arsort($kategoriPengeluaran);
        $topKategoriPengeluaran = array_slice($kategoriPengeluaran, 0, 5, true);
        arsort($kategoriPemasukan);
        $topKategoriPemasukan = array_slice($kategoriPemasukan, 0, 5, true);

        $saran = [];
        if ($savingsRate < 0.1 && $totalPemasukan > 0) {
            $saran[] = 'Tingkatkan savings rate ke > 10% dengan kurangi kategori pengeluaran terbesar.';
        }
        if (!empty($anomali)) {
            $saran[] = 'Terdapat anomali pengeluaran: cek kembali bulan dengan lonjakan.';
        }
        if ($avgPemasukan < $avgPengeluaran) {
            $saran[] = 'Rata-rata pengeluaran melebihi pemasukan. Pertimbangkan penyesuaian anggaran.';
        }

        return view('keuangan.statistik', [
            'tahun' => $year,
            'labels' => $labels,
            'seriesPemasukan' => $seriesPemasukan,
            'seriesPengeluaran' => $seriesPengeluaran,
            'seriesNet' => $seriesNet,
            'cumulativeSaldo' => $cumulativeSaldo,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'totalNet' => $totalNet,
            'avgPemasukan' => $avgPemasukan,
            'avgPengeluaran' => $avgPengeluaran,
            'avgNet' => $avgNet,
            'savingsRate' => $savingsRate,
            'burnRate' => $burnRate,
            'proyeksiAkhirTahun' => $proyeksiAkhirTahun,
            'topKategoriPengeluaran' => $topKategoriPengeluaran,
            'topKategoriPemasukan' => $topKategoriPemasukan,
            'anomali' => $anomali,
            'monthNames' => $monthNames,
            'guestMode' => true,
        ]);
    }

    public function jadwal(Request $request)
    {
        $selectedDate = $request->filled('tanggal')
            ? Carbon::parse($request->input('tanggal'))
            : Carbon::now();

        $calendarMonth = $request->filled('bulan')
            ? Carbon::parse($request->input('bulan') . '-01')
            : $selectedDate->copy()->startOfMonth();

        $startCalendar = $calendarMonth->copy()->startOfMonth()->startOfWeek();
        $endCalendar = $calendarMonth->copy()->endOfMonth()->endOfWeek();

        $jadwals = GuestWorkspace::jadwals();
        $matkuls = GuestWorkspace::matkuls();

        $this->appendMatkulNames($jadwals, $matkuls->keyBy('id'), $matkuls);
        $jadwalsByDate = $this->deduplicateKuliahByDate(
            $this->mapJadwalsByDate($jadwals, $startCalendar, $endCalendar)
        );

        $calendarDays = [];
        $iterate = $startCalendar->copy();
        while ($iterate <= $endCalendar) {
            $calendarDays[] = $iterate->copy();
            $iterate->addDay();
        }

        $selectedDateKey = $selectedDate->toDateString();
        $selectedDayEvents = $this->deduplicateKuliahCollection(
            ($jadwalsByDate[$selectedDateKey] ?? collect())->sortBy('tanggal_mulai')
        );

        return view('jadwal.index', [
            'jadwals' => $jadwals,
            'calendarDays' => $calendarDays,
            'selectedDate' => $selectedDate,
            'calendarMonth' => $calendarMonth,
            'jadwalsByDate' => $jadwalsByDate,
            'selectedDayEvents' => $selectedDayEvents,
            'kegiatanDays' => $this->collectKegiatanDays($jadwals),
            'kegiatanByDate' => $this->collectKegiatanByDate($jadwals),
            'matkuls' => $matkuls,
            'guestMode' => true,
        ]);
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

    private function appendMatkulNames(Collection $jadwals, Collection $matkulMap, ?Collection $matkuls = null): Collection
    {
        $matkulCollection = $matkuls ?: $matkulMap->values();
        foreach ($jadwals as $jadwal) {
            $ids = $jadwal->matkulIds();
            $matkulMeta = $ids->map(fn ($id) => $matkulMap->get((int) $id))->filter();
            $jadwal->matkul_names = $matkulMeta->pluck('nama')->filter()->values()->all();
            $jadwal->primary_matkul = $matkulMeta->first();
            $jadwal->matkul_details = $matkulMeta->values();
        }

        return $matkulCollection;
    }

    private function mapJadwalsByDate(Collection $jadwals, Carbon $startCalendar, Carbon $endCalendar): array
    {
        $map = [];
        foreach ($jadwals as $jadwal) {
            $start = Carbon::parse($jadwal->tanggal_mulai);
            $end = Carbon::parse($jadwal->tanggal_selesai);
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                if ($jadwal->jenis === 'kuliah' && $cursor->isWeekend()) {
                    $cursor->addDay();
                    continue;
                }

                if ($cursor->lt($startCalendar) || $cursor->gt($endCalendar)) {
                    $cursor->addDay();
                    continue;
                }

                $key = $cursor->toDateString();
                if (! isset($map[$key])) {
                    $map[$key] = collect();
                }
                $map[$key]->push($jadwal);
                $cursor->addDay();
            }
        }

        return $map;
    }

    private function deduplicateKuliahByDate(array $map): array
    {
        foreach ($map as $key => $collection) {
            $seen = [];
            $map[$key] = $collection->filter(function ($jadwal) use (&$seen) {
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

        return $map;
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

        return implode('|', [
            $id !== '' ? 'id:' . $id : '',
            $name !== '' ? 'name:' . $name : '',
            $kode !== '' ? 'kode:' . $kode : '',
            $start !== '' ? 'start:' . $start : '',
            $end !== '' ? 'end:' . $end : '',
        ]);
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

    private function collectKegiatanDays(Collection $jadwals): array
    {
        $kegiatanDays = [];
        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $kegiatan->tanggal_deadline
                    ? Carbon::parse($kegiatan->tanggal_deadline)->toDateString()
                    : optional($kegiatan->waktu)->toDateString();
                if (! $tanggal) {
                    continue;
                }
                $kegiatanDays[$tanggal] = true;
            }
        }

        return $kegiatanDays;
    }

    private function collectKegiatanByDate(Collection $jadwals): array
    {
        $map = [];
        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $kegiatan->tanggal_deadline
                    ? Carbon::parse($kegiatan->tanggal_deadline)->toDateString()
                    : optional($kegiatan->waktu)->toDateString();
                if (! $tanggal) {
                    continue;
                }
                if (! isset($map[$tanggal])) {
                    $map[$tanggal] = collect();
                }
                $map[$tanggal]->push($kegiatan);
            }
        }

        return $map;
    }
}
