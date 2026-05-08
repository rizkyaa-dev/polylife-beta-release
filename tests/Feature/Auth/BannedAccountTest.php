<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('banned user with valid credentials is redirected to the banned account notice', function () {
    $superAdmin = User::factory()->create([
        'is_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
        'role' => 'super_admin',
    ]);
    $user = User::factory()->create([
        'account_status' => 'banned',
        'banned_at' => now(),
        'banned_by' => $superAdmin->id,
        'ban_reason_code' => 'policy_violation',
        'ban_reason_text' => '<img src=x onerror=alert(1)>',
    ]);

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('account.banned', absolute: false));

    $this->assertAuthenticatedAs($user);

    $response = $this->get(route('account.banned'));

    $response
        ->assertOk()
        ->assertHeader('Cache-Control')
        ->assertSee('Pelanggaran kebijakan')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSee('<img src=x onerror=alert(1)>', false);

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private');
});

test('banned account cannot access authenticated web pages except banned notice and logout', function () {
    $user = User::factory()->create([
        'account_status' => 'banned',
        'banned_at' => now(),
        'ban_reason_code' => 'spam',
        'ban_reason_text' => 'Spam komentar.',
    ]);

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertRedirect(route('account.banned'));

    $this->actingAs($user)
        ->get(route('workspace.home'))
        ->assertRedirect(route('account.banned'));

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('banned notice requires authentication and redirects active users away', function () {
    $this->get(route('account.banned'))
        ->assertRedirect(route('login'));

    $activeUser = User::factory()->create([
        'account_status' => 'active',
        'banned_at' => null,
    ]);

    $this->actingAs($activeUser)
        ->get(route('account.banned'))
        ->assertRedirect(route('workspace.home'));
});
