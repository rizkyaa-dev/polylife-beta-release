<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\Keuangan;
use App\Models\User;

test('api keuangan index returns mobile-compatible payload', function () {
    $user = User::factory()->create();

    Keuangan::factory()->for($user)->create([
        'jenis' => 'pemasukan',
        'kategori' => 'Uang Saku',
        'nominal' => 100000,
        'tanggal' => '2026-04-05',
    ]);

    Keuangan::factory()->for($user)->create([
        'jenis' => 'pengeluaran',
        'kategori' => 'Makan',
        'nominal' => 25000,
        'tanggal' => '2026-04-07',
    ]);

    Keuangan::factory()->for($user)->create([
        'jenis' => 'pengeluaran',
        'kategori' => 'Transport',
        'nominal' => 15000,
        'tanggal' => '2026-03-28',
    ]);

    $token = $user->createToken(
        'flutter-test',
        [AuthController::MOBILE_API_ABILITY],
        now()->addDay()
    )->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson(route('api.v1.keuangan.index', [
            'bulan' => '2026-04',
            'per_page' => 100,
        ]));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'jenis', 'kategori', 'deskripsi', 'nominal', 'tanggal'],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'selected_month',
                'month_options',
                'summary' => ['total_pemasukan', 'total_pengeluaran', 'saldo'],
            ],
            'links' => ['next', 'prev'],
        ])
        ->assertJsonPath('meta.selected_month', '2026-04')
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('meta.month_options'))->pluck('value')->all())
        ->toContain('2026-04');
    expect((float) $response->json('meta.summary.total_pemasukan'))->toBe(100000.0);
    expect((float) $response->json('meta.summary.total_pengeluaran'))->toBe(25000.0);
    expect((float) $response->json('meta.summary.saldo'))->toBe(75000.0);
});
