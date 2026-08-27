<?php

use App\Models\Keuangan;
use App\Models\User;

it('renders keuangan edit form with formatted nominal display and clean hidden value', function () {
    $user = User::factory()->create();

    $keuangan = Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Makan',
        'nominal' => 420000.00,
        'deskripsi' => 'Makan siang',
        'tanggal' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.edit', $keuangan));

    $response->assertOk();
    $response->assertSee('id="nominal_display"', false);
    $response->assertSee('value="420.000"', false);
    $response->assertSee('id="nominal"', false);
    $response->assertSee('value="420000"', false);
    $response->assertDontSee('value="420000.00"', false);
    $response->assertSee('<span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Rp</span>', false);
});

it('renders keuangan create form with Rp prefix and nominal display input', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('keuangan.create', ['jenis' => 'pengeluaran']));

    $response->assertOk();
    $response->assertSee('id="nominal_display"', false);
    $response->assertSee('id="nominal"', false);
    $response->assertSee('<span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Rp</span>', false);
});
