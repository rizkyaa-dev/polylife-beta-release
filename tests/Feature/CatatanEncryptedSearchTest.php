<?php

use App\Models\Catatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('catatan content is encrypted while manage search can find content tokens', function () {
    $user = User::factory()->create();

    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Rangkuman Mingguan',
        'isi' => 'Materi rahasia tentang struktur data graph traversal.',
        'preview_isi' => Catatan::makePreviewIsi('Materi rahasia tentang struktur data graph traversal.'),
        'tanggal' => '2026-05-10',
        'status_sampah' => false,
    ]);

    $raw = DB::table('catatans')->where('id', $catatan->id)->first();

    expect($raw->isi)->not->toContain('struktur data graph')
        ->and($raw->preview_isi)->toBe('')
        ->and(DB::table('catatan_search_tokens')->where('catatan_id', $catatan->id)->count())->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('catatan.manage', ['q' => 'graph traversal']))
        ->assertOk()
        ->assertSee('Rangkuman Mingguan')
        ->assertDontSee('Materi rahasia tentang struktur data graph traversal.');
});

test('catatan preview toggle stores only preference and renders runtime preview', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('catatan.store'), [
        'judul' => 'Preview Aman',
        'isi' => 'Kalimat privat untuk preview runtime.',
        'tanggal' => '2026-05-10',
        'show_preview' => '1',
    ]);

    $response->assertRedirect(route('catatan.index'));

    $catatan = Catatan::query()->where('judul', 'Preview Aman')->firstOrFail();
    $raw = DB::table('catatans')->where('id', $catatan->id)->first();

    expect($catatan->show_preview)->toBeTrue()
        ->and($catatan->previewForDisplay())->toBe('Kalimat privat untuk preview runtime.')
        ->and($raw->preview_isi)->toBe('')
        ->and($raw->isi)->not->toContain('Kalimat privat');

    $this->actingAs($user)
        ->get(route('catatan.index'))
        ->assertOk()
        ->assertSee('Kalimat privat untuk preview runtime.');
});

test('catatan permanent delete removes database row and search tokens', function () {
    $user = User::factory()->create();
    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Hapus Permanen',
        'isi' => 'Isi yang harus benar benar hilang.',
        'preview_isi' => '',
        'tanggal' => '2026-05-10',
        'status_sampah' => true,
    ]);

    expect(DB::table('catatan_search_tokens')->where('catatan_id', $catatan->id)->count())->toBeGreaterThan(0);

    $this->actingAs($user)
        ->delete(route('catatan.force-delete', $catatan))
        ->assertRedirect(route('catatan.sampah'));

    expect(DB::table('catatans')->where('id', $catatan->id)->exists())->toBeFalse()
        ->and(DB::table('catatan_search_tokens')->where('catatan_id', $catatan->id)->exists())->toBeFalse();
});
