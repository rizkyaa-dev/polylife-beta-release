<?php

use App\Models\Ipk;
use App\Models\Matkul;
use App\Models\User;

it('creates IPS targets and attaches selected matkul snapshots', function () {
    $user = User::factory()->create();
    $matkulA = createTestMatkul($user, ['kode' => 'KOM101', 'nama' => 'Algoritma', 'semester' => 3]);
    $matkulB = createTestMatkul($user, ['kode' => 'MAT201', 'nama' => 'Kalkulus', 'semester' => 3]);

    $response = $this->actingAs($user)->post(route('ipk.store'), [
        'target_mode' => 'ips',
        'semester' => 3,
        'academic_year' => '2025/2026',
        'ips_target' => 3.7,
        'ipk_target' => 3.8,
        'status' => 'planned',
        'matkul_ids' => [$matkulA->id, $matkulB->id],
    ]);

    $response->assertRedirect(route('ipk.index'));

    $ipk = Ipk::with('courses')->where('user_id', $user->id)->first();
    expect($ipk)->not->toBeNull();
    expect($ipk->target_mode)->toBe('ips');
    expect($ipk->courses)->toHaveCount(2);

    expect($ipk->courses->pluck('matkul_id')->all())
        ->toContain($matkulA->id)
        ->toContain($matkulB->id);
});

it('prevents multiple active IPK targets per user', function () {
    $user = User::factory()->create();
    Ipk::create([
        'user_id' => $user->id,
        'target_mode' => 'ipk',
        'semester' => null,
        'status' => 'planned',
        'ipk_target' => 3.9,
    ]);

    $response = $this->actingAs($user)->post(route('ipk.store'), [
        'target_mode' => 'ipk',
        'ipk_target' => 3.7,
        'status' => 'planned',
    ]);

    $response->assertSessionHasErrors(['target_mode']);
    expect(Ipk::where('user_id', $user->id)->count())->toBe(1);
});

function createTestMatkul(User $user, array $overrides = []): Matkul
{
    $defaults = [
        'kode' => 'TES101',
        'nama' => 'Matkul Uji',
        'kelas' => 'A',
        'dosen' => 'Dosen Penguji',
        'semester' => 1,
        'sks' => 3,
        'hari' => 'Senin',
        'jam_mulai' => '08:00',
        'jam_selesai' => '09:40',
        'ruangan' => 'R101',
        'warna_label' => '#2563eb',
        'catatan' => 'Catatan uji',
    ];

    return Matkul::create(array_merge($defaults, $overrides, ['user_id' => $user->id]));
}
