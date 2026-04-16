<?php

use App\Services\Keuangan\MonthlyFinanceSummaryService;
use Illuminate\Support\Carbon;

test('monthly finance summary service builds totals and filters records outside selected month', function () {
    $service = new MonthlyFinanceSummaryService();

    $summary = $service->build([
        [
            'jenis' => 'pemasukan',
            'nominal' => 1000,
            'tanggal' => Carbon::create(2026, 4, 1),
        ],
        [
            'jenis' => 'pemasukan',
            'nominal' => 500,
            'tanggal' => Carbon::create(2026, 4, 2),
        ],
        [
            'jenis' => 'pengeluaran',
            'nominal' => 300,
            'tanggal' => Carbon::create(2026, 4, 1),
        ],
        [
            'jenis' => 'pengeluaran',
            'nominal' => 400,
            'tanggal' => Carbon::create(2026, 4, 3),
        ],
        [
            'jenis' => 'pengeluaran',
            'nominal' => 999,
            'tanggal' => Carbon::create(2026, 5, 1),
        ],
    ], Carbon::create(2026, 4, 12));

    expect($summary['total_pemasukan'])->toBe(1500.0);
    expect($summary['total_pengeluaran'])->toBe(700.0);
    expect($summary['saldo_bulan_ini'])->toBe(800.0);
    expect($summary['bulan_label'])->toBe('April 2026');
    expect($summary['dataset_grafik']['labels'][0])->toBe('01 Apr');
    expect($summary['dataset_grafik']['pemasukan'][0])->toBe(1000.0);
    expect($summary['dataset_grafik']['pengeluaran'][0])->toBe(300.0);
    expect($summary['dataset_grafik']['pengeluaran'][2])->toBe(400.0);
});
