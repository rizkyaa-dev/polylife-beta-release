<?php

namespace App\Services\Keuangan;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class MonthlyFinanceSummaryService
{
    /**
     * @param  iterable<int, mixed>  $records
     * @return array<string, mixed>
     */
    public function build(iterable $records, Carbon $selectedMonth): array
    {
        $startMonth = $selectedMonth->copy()->startOfMonth();
        $endMonth = $selectedMonth->copy()->endOfMonth();

        $totalPemasukan = 0.0;
        $totalPengeluaran = 0.0;
        $dailyTotals = [];

        foreach ($records as $record) {
            $tanggal = data_get($record, 'tanggal');

            try {
                $tanggal = Carbon::parse($tanggal);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $tanggal->betweenIncluded($startMonth, $endMonth)) {
                continue;
            }

            $jenis = trim((string) data_get($record, 'jenis', ''));
            if (! in_array($jenis, ['pemasukan', 'pengeluaran'], true)) {
                continue;
            }

            $nominal = (float) data_get($record, 'nominal', 0);
            $dateKey = $tanggal->toDateString();
            $dailyTotals[$dateKey][$jenis] = ($dailyTotals[$dateKey][$jenis] ?? 0.0) + $nominal;

            if ($jenis === 'pemasukan') {
                $totalPemasukan += $nominal;
            } else {
                $totalPengeluaran += $nominal;
            }
        }

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];

        foreach (CarbonPeriod::create($startMonth, $endMonth) as $date) {
            $labels[] = $date->format('d M');
            $dateKey = $date->toDateString();

            $seriesPemasukan[] = (float) ($dailyTotals[$dateKey]['pemasukan'] ?? 0);
            $seriesPengeluaran[] = (float) ($dailyTotals[$dateKey]['pengeluaran'] ?? 0);
        }

        return [
            'total_pemasukan' => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'saldo_bulan_ini' => $totalPemasukan - $totalPengeluaran,
            'bulan_label' => $selectedMonth->translatedFormat('F Y'),
            'dataset_grafik' => [
                'labels' => $labels,
                'pemasukan' => $seriesPemasukan,
                'pengeluaran' => $seriesPengeluaran,
            ],
        ];
    }
}
