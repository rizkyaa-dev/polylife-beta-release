<?php

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Keuangan\YearlyStatisticsService;

test('expense anomaly detector handles fewer than 3 months without triggering false positives', function () {
    $service = app(YearlyStatisticsService::class);

    // Only 2 months with expense data
    $records = [
        ['jenis' => 'pengeluaran', 'kategori' => 'Makan', 'nominal' => 200000, 'tanggal' => '2026-01-15'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Makan', 'nominal' => 5000000, 'tanggal' => '2026-02-15'],
    ];

    $result = $service->build($records, 2026);

    // With fewer than 3 sample points, statistically valid outlier detection is skipped
    expect($result['anomali'])->toBeArray()->toBeEmpty();
});

test('expense anomaly detector handles zero standard deviation gracefully without division by zero', function () {
    $service = app(YearlyStatisticsService::class);

    // 4 months with identical expense values (standard deviation = 0.0)
    $records = [
        ['jenis' => 'pengeluaran', 'kategori' => 'Sewa Kos', 'nominal' => 1000000, 'tanggal' => '2026-01-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Sewa Kos', 'nominal' => 1000000, 'tanggal' => '2026-02-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Sewa Kos', 'nominal' => 1000000, 'tanggal' => '2026-03-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Sewa Kos', 'nominal' => 1000000, 'tanggal' => '2026-04-10'],
    ];

    $result = $service->build($records, 2026);

    expect($result['anomali'])->toBeArray()->toBeEmpty();
});

test('expense anomaly detector accurately flags statistical outliers beyond mean plus 1.5 standard deviations', function () {
    $service = app(YearlyStatisticsService::class);

    // Baseline 5 months with moderate expenses (~500,000) and 1 extreme outlier in June (8,000,000)
    $records = [
        ['jenis' => 'pengeluaran', 'kategori' => 'Kebutuhan', 'nominal' => 500000, 'tanggal' => '2026-01-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Kebutuhan', 'nominal' => 550000, 'tanggal' => '2026-02-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Kebutuhan', 'nominal' => 480000, 'tanggal' => '2026-03-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Kebutuhan', 'nominal' => 520000, 'tanggal' => '2026-04-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Kebutuhan', 'nominal' => 500000, 'tanggal' => '2026-05-10'],
        ['jenis' => 'pengeluaran', 'kategori' => 'Laptop Baru', 'nominal' => 8000000, 'tanggal' => '2026-06-15'],
    ];

    $result = $service->build($records, 2026);

    expect($result['anomali'])->toBeArray()->toHaveCount(1);
    expect($result['anomali'][0]['bulan'])->toBe('Jun');
    expect((float) $result['anomali'][0]['nilai'])->toBe(8000000.0);
    expect($result['anomali'][0]['batas'])->toBeLessThan(8000000.0);
});

test('keuangan transactions strictly isolate statistics and anomaly calculations between users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // User A has stable normal transactions
    foreach (range(1, 5) as $m) {
        Keuangan::create([
            'user_id' => $userA->id,
            'jenis' => 'pengeluaran',
            'kategori' => 'Makan',
            'nominal' => 500000,
            'tanggal' => sprintf('2026-%02d-10', $m),
        ]);
    }

    // User B has a massive transaction that would be an anomaly
    Keuangan::create([
        'user_id' => $userB->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Mobil',
        'nominal' => 100000000,
        'tanggal' => '2026-03-15',
    ]);

    $responseA = $this->actingAs($userA)->get(route('keuangan.statistik', ['tahun' => 2026]));
    $responseA->assertOk();

    // User A's view should NOT show User B's 100,000,000 or flag any anomaly
    $responseA->assertDontSee('100.000.000');
    $responseA->assertSee('Tidak ada anomali terdeteksi.');

    // Also verify negative nominal validation on store
    $this->actingAs($userA)
        ->post(route('keuangan.store'), [
            'jenis' => 'pengeluaran',
            'kategori' => 'Belanja',
            'nominal' => -1000,
            'tanggal' => '2026-05-01',
        ])
        ->assertSessionHasErrors(['nominal']);
});
