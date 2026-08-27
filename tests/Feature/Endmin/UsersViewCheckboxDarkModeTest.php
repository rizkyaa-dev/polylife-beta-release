<?php

use App\Models\User;

test('endmin users index uses endmin-checkbox styling for dark mode support', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $user = User::factory()->create([
        'account_status' => 'active',
    ]);

    $response = $this->actingAs($superAdmin)
        ->get(route('endmin.users.index'));

    $response->assertOk()
        ->assertSee('id="select-all-users"', false)
        ->assertSee('class="endmin-checkbox"', false)
        ->assertSee('class="bulk-user-checkbox endmin-checkbox"', false);
});

test('endmin verifications index uses endmin-checkbox styling for dark mode support', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
        'account_status' => 'active',
    ]);
    $user = User::factory()->create([
        'account_status' => 'active',
    ]);

    $response = $this->actingAs($superAdmin)
        ->get(route('endmin.verifications.index'));

    $response->assertOk()
        ->assertSee('name="email_verified"', false)
        ->assertSee('class="endmin-checkbox"', false);
});
