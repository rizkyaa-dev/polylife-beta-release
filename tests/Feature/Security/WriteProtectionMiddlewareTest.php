<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Cache::flush();

    Route::middleware(['auth', 'throttle:workspace-write'])->post('/__test/workspace-write-limit', function () {
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth', 'throttle:api-write'])->post('/__test/api-write-limit', function () {
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth', 'throttle:bulk-write'])->post('/__test/bulk-write-limit', function () {
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth', 'prevent-duplicate-write'])->post('/__test/duplicate-write-a', function () {
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth', 'prevent-duplicate-write'])->post('/__test/duplicate-write-b', function () {
        return response()->json(['ok' => true]);
    });
});

afterEach(function () {
    Cache::flush();
});

test('workspace write limiter allows thirty requests per minute per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->postJson('/__test/workspace-write-limit', ['attempt' => $attempt])->assertOk();
    }

    $this->postJson('/__test/workspace-write-limit', ['attempt' => 31])
        ->assertStatus(429);
});

test('api write limiter allows thirty requests per minute per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->postJson('/__test/api-write-limit', ['attempt' => $attempt])->assertOk();
    }

    $this->postJson('/__test/api-write-limit', ['attempt' => 31])
        ->assertStatus(429);
});

test('bulk write limiter allows five requests per minute per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/__test/bulk-write-limit', ['attempt' => $attempt])->assertOk();
    }

    $this->postJson('/__test/bulk-write-limit', ['attempt' => 6])
        ->assertStatus(429);
});

test('duplicate write middleware blocks identical payloads for the same route within three seconds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = [
        'judul' => 'Catatan singkat',
        'isi' => 'Isi yang sama',
    ];

    $this->postJson('/__test/duplicate-write-a', $payload)->assertOk();

    $this->postJson('/__test/duplicate-write-a', $payload)
        ->assertStatus(429)
        ->assertJson([
            'message' => 'Permintaan yang sama baru saja dikirim. Tunggu 3 detik lalu coba lagi.',
            'retry_after' => 3,
        ]);
});

test('duplicate write middleware renders the custom 429 page for web requests', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = [
        'judul' => 'Catatan singkat',
        'isi' => 'Isi yang sama',
    ];

    $this->post('/__test/duplicate-write-a', $payload)->assertOk();

    $this->post('/__test/duplicate-write-a', $payload)
        ->assertStatus(429)
        ->assertSee('Terlalu banyak aksi dalam waktu singkat', false)
        ->assertSee('Coba lagi dalam 3 detik.', false)
        ->assertHeader('Retry-After', '3');
});

test('duplicate write middleware allows different payloads and different routes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/__test/duplicate-write-a', [
        'judul' => 'Payload pertama',
        'isi' => 'Alpha',
    ])->assertOk();

    $this->postJson('/__test/duplicate-write-a', [
        'judul' => 'Payload kedua',
        'isi' => 'Beta',
    ])->assertOk();

    $this->postJson('/__test/duplicate-write-b', [
        'judul' => 'Payload pertama',
        'isi' => 'Alpha',
    ])->assertOk();
});

test('duplicate write middleware allows the same payload again after the cooldown window', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $payload = [
        'judul' => 'Pending',
        'isi' => 'Tunggu jeda',
    ];

    $this->postJson('/__test/duplicate-write-a', $payload)->assertOk();

    $this->travel(4)->seconds();

    $this->postJson('/__test/duplicate-write-a', $payload)->assertOk();
});
