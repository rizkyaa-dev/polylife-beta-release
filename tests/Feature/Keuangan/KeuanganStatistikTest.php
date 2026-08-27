<?php

use App\Models\Keuangan;
use App\Models\User;

test('keuangan statistik includes historical carry-over balance in cumulative saldo', function () {
    $user = User::factory()->create();

    // 2025 carry-over: net 10,000,000
    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pemasukan',
        'kategori' => 'Gaji',
        'nominal' => 10000000,
        'tanggal' => '2025-12-15',
    ]);

    // 2026 transactions: Jan income 2,000,000 and expense 1,000,000
    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pemasukan',
        'kategori' => 'Side Project',
        'nominal' => 2000000,
        'tanggal' => '2026-01-10',
    ]);

    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Belanja',
        'nominal' => 1000000,
        'tanggal' => '2026-01-20',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.statistik', ['tahun' => 2026]));

    $response->assertOk();
    // In Jan 2026, cumulative saldo must be 10,000,000 + (2,000,000 - 1,000,000) = 11,000,000
    $response->assertSee('"saldo":[11000000', false);
});

test('keuangan statistik clamps negative savings rate and handles deficit gracefully', function () {
    $user = User::factory()->create();

    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pemasukan',
        'kategori' => 'Saku',
        'nominal' => 500000,
        'tanggal' => '2026-01-10',
    ]);

    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Sewa',
        'nominal' => 2500000,
        'tanggal' => '2026-01-15',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.statistik', ['tahun' => 2026]));

    $response->assertOk();
    // Savings rate should be 0.0% instead of -400.0%
    $response->assertSee('0,0%');
    $response->assertDontSee('-400,0%');
});

test('keuangan statistik adjusts projection label for past completed years', function () {
    $user = User::factory()->create();

    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pemasukan',
        'kategori' => 'Gaji',
        'nominal' => 5000000,
        'tanggal' => '2025-05-10',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.statistik', ['tahun' => 2025]));

    $response->assertOk();
    $response->assertSee('Total Bersih Tahunan');
});

test('guest keuangan statistik calculates initial balance and metrics properly', function () {
    $response = $this->get(route('guest.keuangan.statistik', ['tahun' => 2026]));

    $response->assertOk();
    $response->assertSee('Ringkasan Tahunan');
});
