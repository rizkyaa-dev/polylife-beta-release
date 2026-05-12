<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('workspace.home', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('navigation menu can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/workspace');

    $response->assertRedirect(route('workspace.home'));

    $this->get(route('workspace.home'))
        ->assertOk()
        ->assertSee('Jadwal Hari Ini');
});

test('admin cannot access workspace routes', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->get(route('workspace.home'))
        ->assertRedirect(route('admin.dashboard'));
});

test('admin cannot access workspace profile route', function () {
    $admin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_ADMIN,
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->get(route('profile'))
        ->assertRedirect(route('admin.dashboard'));
});

test('super admin cannot access workspace routes', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('workspace.home'))
        ->assertRedirect(route('endmin.dashboard'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect('/login');

    $this->assertGuest();
});
