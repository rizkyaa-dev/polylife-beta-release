<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class KeuanganStatistikController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $year = (int) ($request->get('tahun') ?: Carbon::now()->year);

        $records = Keuangan::where('user_id', $userId)
            ->whereYear('tanggal', $year)
            ->orderBy('tanggal')
            ->get(['jenis', 'kategori', 'nominal', 'tanggal']);

        $months = range(1, 12);
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
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
            $m = (int) Carbon::parse($r->tanggal)->month;
            if ($r->jenis === 'pemasukan') {
                $byMonth[$m]['pemasukan'] += (float) $r->nominal;
                $kategoriPemasukan[$r->kategori ?: 'Lainnya'] = ($kategoriPemasukan[$r->kategori ?: 'Lainnya'] ?? 0) + (float) $r->nominal;
            } else {
                $byMonth[$m]['pengeluaran'] += (float) $r->nominal;
                $kategoriPengeluaran[$r->kategori ?: 'Lainnya'] = ($kategoriPengeluaran[$r->kategori ?: 'Lainnya'] ?? 0) + (float) $r->nominal;
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
        ]);
    }
}

