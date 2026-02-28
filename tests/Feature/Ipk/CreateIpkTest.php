<?php

use App\Models\Ipk;
use App\Models\User;

it('creates IPS records and normalizes planning fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ipk.store'), [
        'target_mode' => 'ipk',
        'semester' => 1,
        'academic_year' => '2025/2026',
        'ips_actual' => 3.7,
        'ips_target' => 3.9,
        'ipk_target' => 4.0,
        'status' => 'planned',
        'remarks' => 'Semester pertama',
    ]);

    $response->assertRedirect(route('ipk.index'));

    $ipk = Ipk::where('user_id', $user->id)->first();
    expect($ipk)->not->toBeNull();
    expect($ipk->semester)->toBe(1);
    expect((float) $ipk->ips_actual)->toBe(3.7);
    expect($ipk->target_mode)->toBe('ips');
    expect($ipk->status)->toBe('final');
    expect($ipk->ips_target)->toBeNull();
    expect($ipk->ipk_target)->toBeNull();
    expect((float) $ipk->ipk_running)->toBe(3.7);
});

it('prevents non sequential semester insertion', function () {
    $user = User::factory()->create();
    Ipk::create([
        'user_id' => $user->id,
        'target_mode' => 'ips',
        'semester' => 1,
        'status' => 'final',
        'ips_actual' => 3.2,
    ]);

    $response = $this->actingAs($user)->post(route('ipk.store'), [
        'semester' => 3,
        'academic_year' => '2025/2026',
        'ips_actual' => 3.7,
    ]);

    $response->assertSessionHasErrors(['semester']);
    expect(Ipk::where('user_id', $user->id)->count())->toBe(1);
});
