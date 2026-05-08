<?php

use App\Models\User;

test('super admin cannot ban the currently authenticated account', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);

    $this->actingAs($superAdmin)
        ->from(route('endmin.users.edit', $superAdmin))
        ->put(route('endmin.users.update', $superAdmin), [
            'email' => $superAdmin->email,
            'account_status' => 'banned',
            'ban_reason_code' => 'self_ban',
            'ban_reason_text' => 'Tidak sengaja.',
        ])
        ->assertRedirect(route('endmin.users.edit', $superAdmin))
        ->assertSessionHasErrors('account_status');

    expect($superAdmin->refresh()->account_status)->toBe('active');
});

test('super admin cannot ban another super admin account', function () {
    $actor = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $target = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);

    $this->actingAs($actor)
        ->from(route('endmin.users.edit', $target))
        ->put(route('endmin.users.update', $target), [
            'email' => $target->email,
            'account_status' => 'banned',
            'ban_reason_code' => 'peer_ban',
            'ban_reason_text' => 'Tidak diizinkan.',
        ])
        ->assertRedirect(route('endmin.users.edit', $target))
        ->assertSessionHasErrors('account_status');

    expect($target->refresh()->account_status)->toBe('active');
});
