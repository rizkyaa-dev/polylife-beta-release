<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

test('mobile api user can register and receives verification email', function () {
    Notification::fake();

    $response = $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Mahasiswa PolyLife',
        'email' => 'mahasiswa@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'mahasiswa@example.test')
        ->assertJsonMissingPath('data.access_token');

    $user = User::query()->where('email', 'mahasiswa@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('mobile api register validates duplicate email', function () {
    $user = User::factory()->create(['email' => 'taken@example.test']);

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Mahasiswa PolyLife',
        'email' => strtoupper($user->email),
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('mobile api forgot password sends reset link with generic response', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.test']);

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Jika email terdaftar, link reset password akan dikirim ke email tersebut.',
        ]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('mobile api forgot password does not reveal unknown email', function () {
    Notification::fake();

    $this->postJson(route('api.v1.auth.forgot-password'), [
        'email' => 'unknown@example.test',
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Jika email terdaftar, link reset password akan dikirim ke email tersebut.',
        ]);

    Notification::assertNothingSent();
});
