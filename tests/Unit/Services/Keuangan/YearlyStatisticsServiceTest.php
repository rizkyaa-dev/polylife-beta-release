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

test('yearly statistics service detects expense spikes using populated expense months', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 7));

    try {
        $service = new YearlyStatisticsService();
        $monthlyExpenses = [
            1 => 3565000,
            2 => 5570000,
            3 => 3240000,
            4 => 4320000,
            5 => 3090000,
            6 => 3405000,
            7 => 3200000,
            8 => 6260000,
            9 => 4025000,
            10 => 3070000,
            11 => 3230000,
            12 => 4545000,
        ];

        $records = [];
        foreach ($monthlyExpenses as $month => $amount) {
            $records[] = [
                'jenis' => 'pengeluaran',
                'kategori' => 'Bulanan',
                'nominal' => $amount,
                'tanggal' => Carbon::create(2026, $month, 10),
            ];
        }

        $statistics = $service->build($records, 2026);

        expect(array_column($statistics['anomali'], 'bulan'))->toBe(['Feb', 'Agu']);
        expect($statistics['anomali'][0]['nilai'])->toEqual(5570000);
        expect($statistics['anomali'][1]['nilai'])->toEqual(6260000);
    } finally {
        Carbon::setTestNow();
    }
});

test('yearly statistics service does not mark anomalies when expense sample is too small', function () {
    $service = new YearlyStatisticsService();

    $statistics = $service->build([
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Kos',
            'nominal' => 1000000,
            'tanggal' => Carbon::create(2026, 1, 5),
        ],
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Darurat',
            'nominal' => 5000000,
            'tanggal' => Carbon::create(2026, 2, 5),
        ],
    ], 2026);

    expect($statistics['anomali'])->toBe([]);
});

test('yearly statistics service ignores empty months when detecting anomalies', function () {
    $service = new YearlyStatisticsService();

    $statistics = $service->build([
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Makan',
            'nominal' => 1000000,
            'tanggal' => Carbon::create(2026, 1, 10),
        ],
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Makan',
            'nominal' => 1100000,
            'tanggal' => Carbon::create(2026, 3, 10),
        ],
        [
            'jenis' => 'pengeluaran',
            'kategori' => 'Makan',
            'nominal' => 1050000,
            'tanggal' => Carbon::create(2026, 6, 10),
        ],
    ], 2026);

    expect($statistics['anomali'])->toBe([]);
});
