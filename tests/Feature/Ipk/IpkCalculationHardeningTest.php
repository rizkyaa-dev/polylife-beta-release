<?php

use App\Models\Ipk;
use App\Models\User;

test('ipk validation enforces boundary values for ips and semester', function () {
    $user = User::factory()->create();

    // Valid lower boundary: IPS 0.00, Semester 1
    $this->actingAs($user)
        ->post(route('ipk.store'), [
            'semester' => 1,
            'academic_year' => '2025/2026',
            'ips_actual' => 0.0,
        ])
        ->assertRedirect(route('ipk.index'));

    $ipk1 = Ipk::where('user_id', $user->id)->where('semester', 1)->first();
    expect((float) $ipk1->ips_actual)->toBe(0.0);
    expect((float) $ipk1->ipk_running)->toBe(0.0);

    // Invalid upper boundary: IPS 4.01 should fail validation
    $this->actingAs($user)
        ->post(route('ipk.store'), [
            'semester' => 2,
            'academic_year' => '2025/2026',
            'ips_actual' => 4.01,
        ])
        ->assertSessionHasErrors(['ips_actual']);

    // Invalid lower boundary: negative IPS -0.5 should fail validation
    $this->actingAs($user)
        ->post(route('ipk.store'), [
            'semester' => 2,
            'academic_year' => '2025/2026',
            'ips_actual' => -0.5,
        ])
        ->assertSessionHasErrors(['ips_actual']);

    // Valid upper boundary: IPS 4.00, Semester 2
    $this->actingAs($user)
        ->post(route('ipk.store'), [
            'semester' => 2,
            'academic_year' => '2025/2026',
            'ips_actual' => 4.0,
        ])
        ->assertRedirect(route('ipk.index'));

    $ipk2 = Ipk::where('user_id', $user->id)->where('semester', 2)->first();
    expect((float) $ipk2->ips_actual)->toBe(4.0);
    expect((float) $ipk2->ipk_running)->toBe(2.0); // (0.0 + 4.0) / 2 = 2.0
});

test('ipk update cascades recalculation across all subsequent semesters', function () {
    $user = User::factory()->create();

    // Create Semester 1 (IPS: 3.00)
    $this->actingAs($user)->post(route('ipk.store'), [
        'semester' => 1,
        'academic_year' => '2025/2026',
        'ips_actual' => 3.0,
    ])->assertRedirect(route('ipk.index'));

    // Create Semester 2 (IPS: 3.50)
    $this->actingAs($user)->post(route('ipk.store'), [
        'semester' => 2,
        'academic_year' => '2025/2026',
        'ips_actual' => 3.5,
    ])->assertRedirect(route('ipk.index'));

    // Create Semester 3 (IPS: 3.70)
    $this->actingAs($user)->post(route('ipk.store'), [
        'semester' => 3,
        'academic_year' => '2026/2027',
        'ips_actual' => 3.7,
    ])->assertRedirect(route('ipk.index'));

    $sem1 = Ipk::where('user_id', $user->id)->where('semester', 1)->first();
    $sem2 = Ipk::where('user_id', $user->id)->where('semester', 2)->first();
    $sem3 = Ipk::where('user_id', $user->id)->where('semester', 3)->first();

    expect((float) $sem1->ipk_running)->toBe(3.0);
    expect((float) $sem2->ipk_running)->toBe(3.25); // (3.0 + 3.5) / 2 = 3.25
    expect((float) $sem3->ipk_running)->toBe(3.4);  // (3.0 + 3.5 + 3.7) / 3 = 3.40

    // Now update Semester 1 from 3.0 to 4.0
    $this->actingAs($user)->put(route('ipk.update', $sem1->id), [
        'semester' => 1,
        'academic_year' => '2025/2026',
        'ips_actual' => 4.0,
    ])->assertRedirect(route('ipk.index'));

    // Verify all subsequent records automatically cascaded their ipk_running
    expect((float) $sem1->fresh()->ipk_running)->toBe(4.0);
    expect((float) $sem2->fresh()->ipk_running)->toBe(3.75); // (4.0 + 3.5) / 2 = 3.75
    expect((float) $sem3->fresh()->ipk_running)->toBe(3.73); // (4.0 + 3.5 + 3.7) / 3 = 3.733... -> 3.73
});

test('ipk strictly isolates access between different users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $ipkA = Ipk::create([
        'user_id' => $userA->id,
        'semester' => 1,
        'academic_year' => '2025/2026',
        'ips_actual' => 3.8,
        'target_mode' => 'ips',
        'status' => 'final',
    ]);

    // User B cannot edit User A's IPK record
    $this->actingAs($userB)
        ->get(route('ipk.edit', $ipkA->id))
        ->assertStatus(403);

    // User B cannot update User A's IPK record
    $this->actingAs($userB)
        ->put(route('ipk.update', $ipkA->id), [
            'semester' => 1,
            'academic_year' => '2025/2026',
            'ips_actual' => 2.0,
        ])
        ->assertStatus(403);

    expect((float) $ipkA->fresh()->ips_actual)->toBe(3.8);

    // User B cannot delete User A's IPK record
    $this->actingAs($userB)
        ->delete(route('ipk.destroy', $ipkA->id))
        ->assertStatus(403);

    expect($ipkA->fresh())->not->toBeNull();

    // User B can create their own Semester 1 without conflict
    $this->actingAs($userB)
        ->post(route('ipk.store'), [
            'semester' => 1,
            'academic_year' => '2025/2026',
            'ips_actual' => 3.9,
        ])
        ->assertRedirect(route('ipk.index'));

    expect(Ipk::where('user_id', $userB->id)->where('semester', 1)->count())->toBe(1);
    expect(Ipk::where('user_id', $userA->id)->where('semester', 1)->count())->toBe(1);
});
