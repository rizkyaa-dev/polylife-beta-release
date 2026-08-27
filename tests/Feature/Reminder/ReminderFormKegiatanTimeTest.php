<?php

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\User;

it('renders reminder create form with correct kegiatan time instead of 00:00', function () {
    $user = User::factory()->create();

    $jadwal = Jadwal::create([
        'user_id' => $user->id,
        'jenis' => 'kuliah',
        'catatan_tambahan' => 'Jadwal Kuliah',
        'tanggal_mulai' => '2026-08-20',
        'tanggal_selesai' => '2026-08-31',
    ]);

    $kegiatan = Kegiatan::create([
        'jadwal_id' => $jadwal->id,
        'nama_kegiatan' => 'seminar le',
        'tanggal_deadline' => '2026-08-29',
        'waktu' => '15:44:00',
        'status' => 'belum_dimulai',
    ]);

    $response = $this->actingAs($user)->get(route('reminder.create'));

    $response->assertOk();
    $response->assertSee('seminar le • 29 Aug 15:44', false);
    $response->assertDontSee('seminar le • 29 Aug 00:00', false);
});

it('returns correct kegiatan time in api reminder options', function () {
    $user = User::factory()->create();

    $jadwal = Jadwal::create([
        'user_id' => $user->id,
        'jenis' => 'kuliah',
        'catatan_tambahan' => 'Jadwal Kuliah',
        'tanggal_mulai' => '2026-08-20',
        'tanggal_selesai' => '2026-08-31',
    ]);

    Kegiatan::create([
        'jadwal_id' => $jadwal->id,
        'nama_kegiatan' => 'seminar le',
        'tanggal_deadline' => '2026-08-29',
        'waktu' => '15:44:00',
        'status' => 'belum_dimulai',
    ]);

    $token = $user->createToken('test', [\App\Http\Controllers\Api\AuthController::MOBILE_API_ABILITY], now()->addDay())->plainTextToken;

    $response = $this->withToken($token)->getJson(route('api.v1.reminder.options'));

    $response->assertOk();
    $response->assertJsonPath('data.targets.3.options.0.label', 'seminar le • 29 Aug 15:44');
    $response->assertDontSee('00:00', false);
});
