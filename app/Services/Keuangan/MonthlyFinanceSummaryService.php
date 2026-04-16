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

        $normalized = collect($records)
            ->map(function (mixed $record) {
                $tanggal = data_get($record, 'tanggal');

                try {
                    $tanggal = Carbon::parse($tanggal);
                } catch (\Throwable $e) {
                    $tanggal = null;
                }

                return [
                    'jenis' => trim((string) data_get($record, 'jenis', '')),
                    'nominal' => (float) data_get($record, 'nominal', 0),
                    'tanggal' => $tanggal,
                ];
            })
            ->filter(fn (array $record) => $record['tanggal'] instanceof Carbon)
            ->filter(fn (array $record) => $record['tanggal']->betweenIncluded($startMonth, $endMonth))
            ->values();

        $totalPemasukan = (float) $normalized->where('jenis', 'pemasukan')->sum('nominal');
        $totalPengeluaran = (float) $normalized->where('jenis', 'pengeluaran')->sum('nominal');

        $labels = [];
        $seriesPemasukan = [];
        $seriesPengeluaran = [];

        foreach (CarbonPeriod::create($startMonth, $endMonth) as $date) {
            $labels[] = $date->format('d M');
            $dateKey = $date->toDateString();

            $seriesPemasukan[] = (float) $normalized
                ->where('jenis', 'pemasukan')
                ->filter(fn (array $record) => $record['tanggal']->toDateString() === $dateKey)
                ->sum('nominal');

            $seriesPengeluaran[] = (float) $normalized
                ->where('jenis', 'pengeluaran')
                ->filter(fn (array $record) => $record['tanggal']->toDateString() === $dateKey)
                ->sum('nominal');
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
