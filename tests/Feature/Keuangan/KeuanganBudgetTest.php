<?php

use App\Models\Keuangan;
use App\Models\KeuanganBudget;
use App\Models\User;
use App\Services\Keuangan\BudgetEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('authenticated user can view anggaran page and default evaluation metrics', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.anggaran'));

    $response->assertOk();
    $response->assertViewIs('keuangan.anggaran');
    $response->assertSee('Modul Keuangan');
    $response->assertSee('Plafon Anggaran');
    $response->assertSee('Buku Kas');
    $response->assertSee('Statistik &amp; Tren', false);
    $response->assertSee('id="nominal_limit_display"', false);
    $response->assertSee('name="nominal_limit"', false);
});

test('user can store a new category budget limit', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('keuangan.anggaran.store'), [
        'kategori' => 'Makanan & Minuman',
        'nominal_limit' => 1500000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    $response->assertRedirect(route('keuangan.anggaran', ['bulan' => 8, 'tahun' => 2026]));
    $this->assertDatabaseHas('keuangan_budgets', [
        'user_id' => $user->id,
        'kategori' => 'Makanan & Minuman',
        'nominal_limit' => 1500000.00,
        'bulan' => 8,
        'tahun' => 2026,
    ]);
});

test('storing existing category budget updates the nominal limit without duplicating', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    KeuanganBudget::create([
        'user_id' => $user->id,
        'kategori' => 'Transportasi',
        'nominal_limit' => 200000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    $response = $this->actingAs($user)->post(route('keuangan.anggaran.store'), [
        'kategori' => 'Transportasi',
        'nominal_limit' => 350000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    $response->assertRedirect();
    expect(KeuanganBudget::where('user_id', $user->id)->count())->toBe(1);
    expect(KeuanganBudget::first()->nominal_limit)->toEqual(350000.00);
});

test('budget request validates required fields and minimum limits', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('keuangan.anggaran.store'), [
        'kategori' => '',
        'nominal_limit' => 500, // less than 1000
        'bulan' => 13, // invalid month
        'tahun' => 1999, // invalid year
    ]);

    $response->assertSessionHasErrors(['kategori', 'nominal_limit', 'bulan', 'tahun']);
});

test('user can delete their own category budget', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $budget = KeuanganBudget::create([
        'user_id' => $user->id,
        'kategori' => 'Hiburan',
        'nominal_limit' => 500000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    $response = $this->actingAs($user)->delete(route('keuangan.anggaran.destroy', $budget));

    $response->assertRedirect(route('keuangan.anggaran', ['bulan' => 8, 'tahun' => 2026]));
    $this->assertDatabaseMissing('keuangan_budgets', [
        'id' => $budget->id,
    ]);
});

test('user cannot delete another users category budget', function () {
    $user1 = User::factory()->create(['email_verified_at' => now()]);
    $user2 = User::factory()->create(['email_verified_at' => now()]);

    $budget = KeuanganBudget::create([
        'user_id' => $user2->id,
        'kategori' => 'Kos',
        'nominal_limit' => 1000000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    $response = $this->actingAs($user1)->delete(route('keuangan.anggaran.destroy', $budget));
    $response->assertForbidden();
    $this->assertDatabaseHas('keuangan_budgets', ['id' => $budget->id]);
});

test('budget evaluation service calculates progress and health status accurately', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    KeuanganBudget::create([
        'user_id' => $user->id,
        'kategori' => 'Makanan',
        'nominal_limit' => 1000000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    KeuanganBudget::create([
        'user_id' => $user->id,
        'kategori' => 'Transportasi',
        'nominal_limit' => 200000,
        'bulan' => 8,
        'tahun' => 2026,
    ]);

    // Add spending: Makanan = 600.000 (60% - aman), Transportasi = 250.000 (125% - over)
    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Makanan',
        'nominal' => 600000,
        'tanggal' => '2026-08-10',
    ]);

    Keuangan::create([
        'user_id' => $user->id,
        'jenis' => 'pengeluaran',
        'kategori' => 'Transportasi',
        'nominal' => 250000,
        'tanggal' => '2026-08-15',
    ]);

    $service = app(BudgetEvaluationService::class);
    $result = $service->evaluateUser($user->id, 8, 2026);

    expect($result['total_limit'])->toEqual(1200000.00);
    expect($result['total_spent'])->toEqual(850000.00);
    expect($result['overbudget_count'])->toBe(1);
    expect($result['health_status'])->toBe('Kritis / Defisit');
    expect($result['health_color'])->toBe('rose');

    $items = collect($result['items'])->keyBy('kategori');
    expect($items['Makanan']['percentage'])->toEqual(60.0);
    expect($items['Makanan']['status'])->toBe('aman');
    expect($items['Transportasi']['percentage'])->toEqual(125.0);
    expect($items['Transportasi']['status'])->toBe('over');
    expect($items['Transportasi']['overbudget'])->toEqual(50000.00);
});

test('guest can access guest keuangan anggaran page', function () {
    $response = $this->get(route('guest.keuangan.anggaran'));

    $response->assertOk();
    $response->assertViewIs('keuangan.anggaran');
    $response->assertSee('Mode Tamu');
});
