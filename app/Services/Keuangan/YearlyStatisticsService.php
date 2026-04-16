<?php

namespace App\Services\Keuangan;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class YearlyStatisticsService
{
    /**
     * @param  iterable<int, array<string, mixed>|object>  $records
     * @return array<string, mixed>
     */
    public function build(iterable $records, int $year): array
    {
        $normalizedRecords = collect($records)
            ->map(fn ($record) => $this->normalizeRecord($record))
            ->filter()
            ->values();

        $months = range(1, 12);
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $byMonth = [];
        foreach ($months as $month) {
            $byMonth[$month] = [
                'pemasukan' => 0,
                'pengeluaran' => 0,
                'net' => 0,
            ];
        }

        $kategoriPengeluaran = [];
        $kategoriPemasukan = [];

        foreach ($normalizedRecords as $record) {
            $month = (int) $record['tanggal']->month;
            if ($record['jenis'] === 'pemasukan') {
                $byMonth[$month]['pemasukan'] += $record['nominal'];
                $kategoriPemasukan[$record['kategori']] = ($kategoriPemasukan[$record['kategori']] ?? 0) + $record['nominal'];
            } else {
                $byMonth[$month]['pengeluaran'] += $record['nominal'];
                $kategoriPengeluaran[$record['kategori']] = ($kategoriPengeluaran[$record['kategori']] ?? 0) + $record['nominal'];
            }
        }

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];
        $seriesNet = [];
        $cumulativeSaldo = [];

        $running = 0;
        foreach ($months as $month) {
            $labels[] = $monthNames[$month];
            $pemasukan = $byMonth[$month]['pemasukan'];
            $pengeluaran = $byMonth[$month]['pengeluaran'];
            $net = $pemasukan - $pengeluaran;
            $seriesPemasukan[] = $pemasukan;
            $seriesPengeluaran[] = $pengeluaran;
            $seriesNet[] = $net;
            $running += $net;
            $cumulativeSaldo[] = $running;
            $byMonth[$month]['net'] = $net;
        }

        $totalPemasukan = array_sum($seriesPemasukan);
        $totalPengeluaran = array_sum($seriesPengeluaran);
        $totalNet = $totalPemasukan - $totalPengeluaran;

        $bulanTerisi = max(1, (int) min(12, Carbon::now()->year === $year ? Carbon::now()->month : 12));
        $avgPemasukan = $bulanTerisi ? $totalPemasukan / $bulanTerisi : 0;
        $avgPengeluaran = $bulanTerisi ? $totalPengeluaran / $bulanTerisi : 0;
        $avgNet = $bulanTerisi ? $totalNet / $bulanTerisi : 0;

        $meanPengeluaran = $avgPengeluaran;
        $variance = 0;
        foreach ($months as $month) {
            $variance += pow(($byMonth[$month]['pengeluaran'] - $meanPengeluaran), 2);
        }

        $stdPengeluaran = sqrt($variance / count($months));
        $anomali = [];
        $threshold = $meanPengeluaran + (1.5 * $stdPengeluaran);
        foreach ($months as $month) {
            if ($byMonth[$month]['pengeluaran'] > $threshold && $byMonth[$month]['pengeluaran'] > 0) {
                $anomali[] = [
                    'bulan' => $monthNames[$month],
                    'nilai' => $byMonth[$month]['pengeluaran'],
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
        if (! empty($anomali)) {
            $saran[] = 'Terdapat anomali pengeluaran: cek kembali bulan dengan lonjakan.';
        }
        if ($avgPemasukan < $avgPengeluaran) {
            $saran[] = 'Rata-rata pengeluaran melebihi pemasukan. Pertimbangkan penyesuaian anggaran.';
        }

        return [
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
            'saran' => $saran,
        ];
    }

    /**
     * @param  array<string, mixed>|object  $record
     * @return array<string, mixed>|null
     */
    private function normalizeRecord(array|object $record): ?array
    {
        $jenis = $this->recordValue($record, 'jenis') ?? 'pengeluaran';
        $kategori = $this->recordValue($record, 'kategori') ?? 'Lainnya';
        $nominal = (float) ($this->recordValue($record, 'nominal') ?? 0);
        $tanggal = $this->recordValue($record, 'tanggal');

        if (! $tanggal instanceof CarbonInterface) {
            try {
                $tanggal = Carbon::parse($tanggal);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return [
            'jenis' => $jenis === 'pemasukan' ? 'pemasukan' : 'pengeluaran',
            'kategori' => trim((string) $kategori) ?: 'Lainnya',
            'nominal' => $nominal,
            'tanggal' => Carbon::instance($tanggal),
        ];
    }

    /**
     * @param  array<string, mixed>|object  $record
     */
    private function recordValue(array|object $record, string $key): mixed
    {
        if (is_array($record)) {
            return $record[$key] ?? null;
        }

        return $record->{$key} ?? null;
    }
}
