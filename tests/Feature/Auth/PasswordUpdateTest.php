<?php

namespace Tests\Feature\Auth;

use App\Notifications\PasswordChangedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('password can be updated', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

test('correct password must be provided to update password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $component
        ->assertHasErrors(['current_password'])
        ->assertNoRedirect();

    Notification::assertNothingSent();
});

test('updating password revokes api tokens and other database sessions', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    $currentSessionId = session()->getId();
    DB::table('sessions')->insert([
        [
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Current Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Other Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $user->tokens()->create([
        'name' => 'mobile',
        'token' => hash('sha256', 'mobile-token'),
        'abilities' => ['*'],
    ]);

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('sessions', [
        'id' => $currentSessionId,
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'other-session-id',
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('password update attempts are rate limited', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});
