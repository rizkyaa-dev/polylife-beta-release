<?php

use App\Services\Keuangan\YearlyStatisticsService;
use Illuminate\Support\Carbon;

test('yearly statistics service groups totals per month and builds suggestions', function () {
    $service = new YearlyStatisticsService();

    $statistics = $service->build([
        [
            'jenis' => 'pemasukan',
            'kategori' => 'Beasiswa',
            'nominal' => 1000,
            'tanggal' => Carbon::create(2026, 1, 10),
        ],
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Kopi',
            'nominal' => 300,
            'tanggal' => Carbon::create(2026, 1, 12),
        ],
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Laptop',
            'nominal' => 1200,
            'tanggal' => Carbon::create(2026, 2, 1),
        ],
    ], 2026);

    expect($statistics['labels'][0])->toBe('Jan');
    expect($statistics['seriesPemasukan'][0])->toBe(1000.0);
    expect($statistics['seriesPengeluaran'][0])->toBe(300.0);
    expect($statistics['seriesPengeluaran'][1])->toBe(1200.0);
    expect($statistics['totalPemasukan'])->toBe(1000.0);
    expect($statistics['totalPengeluaran'])->toBe(1500.0);
    expect($statistics['totalNet'])->toBe(-500.0);
    expect($statistics['topKategoriPengeluaran'])->toMatchArray([
        'Laptop' => 1200.0,
        'Kopi' => 300.0,
    ]);
    expect($statistics['saran'])->toContain('Rata-rata pengeluaran melebihi pemasukan. Pertimbangkan penyesuaian anggaran.');
});
