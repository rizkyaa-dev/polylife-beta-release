<?php

use App\Models\User;

test('api login rejects invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Email atau password salah.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('api login is rate limited after repeated attempts from the same email and ip', function () {
    $user = User::factory()->create();
    $payload = [
        'email' => $user->email,
        'password' => 'wrong-password',
    ];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->postJson(route('api.v1.auth.login'), $payload)
            ->assertStatus(422);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->postJson(route('api.v1.auth.login'), $payload)
        ->assertStatus(429)
        ->assertJsonStructure(['message']);
});

test('api login is rate limited across rotating ips for the same email', function () {
    $user = User::factory()->create();
    $payload = [
        'email' => $user->email,
        'password' => 'wrong-password',
    ];

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $ip = '198.51.100.'.$attempt;

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('api.v1.auth.login'), $payload)
            ->assertStatus(422);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
        ->postJson(route('api.v1.auth.login'), $payload)
        ->assertStatus(429)
        ->assertJsonStructure(['message']);
});

test('api login truncates long user agent when device name is omitted', function () {
    $user = User::factory()->create();
    $userAgent = 'Flutter/'.str_repeat('A', 300);

    $response = $this->withHeaders([
        'User-Agent' => $userAgent,
    ])->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['token_type', 'access_token', 'user'],
        ]);

    $token = $user->fresh()->tokens()->first();

    expect($token)->not->toBeNull();
    expect($token->name)->toBe(substr($userAgent, 0, 120));
    expect(strlen($token->name))->toBe(120);
});
