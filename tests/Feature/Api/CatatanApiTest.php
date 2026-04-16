<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\Catatan;
use App\Models\User;

test('api catatan index returns preview payload while show returns full content', function () {
    $user = User::factory()->create();
    $isi = str_repeat('Konten catatan beta yang cukup panjang untuk mengetes preview. ', 4);

    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Catatan Pengujian',
        'isi' => $isi,
        'preview_isi' => Catatan::makePreviewIsi($isi),
        'tanggal' => '2026-04-12',
        'status_sampah' => false,
    ]);

    $token = $user->createToken(
        'flutter-test',
        [AuthController::MOBILE_API_ABILITY],
        now()->addDay()
    )->plainTextToken;

    $indexResponse = $this
        ->withToken($token)
        ->getJson(route('api.v1.catatan.index', ['per_page' => 100]));

    $indexResponse
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'judul', 'preview_isi', 'has_full_isi', 'tanggal', 'status_sampah'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'trash_count'],
            'links' => ['next', 'prev'],
        ])
        ->assertJsonPath('data.0.id', $catatan->id)
        ->assertJsonPath('data.0.preview_isi', Catatan::makePreviewIsi($isi))
        ->assertJsonPath('data.0.has_full_isi', false)
        ->assertJsonMissingPath('data.0.isi');

    $showResponse = $this
        ->withToken($token)
        ->getJson(route('api.v1.catatan.show', ['catatan' => $catatan->id]));

    $showResponse
        ->assertOk()
        ->assertJsonPath('data.id', $catatan->id)
        ->assertJsonPath('data.preview_isi', Catatan::makePreviewIsi($isi))
        ->assertJsonPath('data.has_full_isi', true)
        ->assertJsonPath('data.isi', $isi);
});
