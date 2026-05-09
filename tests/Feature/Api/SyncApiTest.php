<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\Catatan;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Str;

function mobileTokenFor(User $user): string
{
    return $user->createToken(
        'flutter-sync-test',
        [AuthController::MOBILE_API_ABILITY],
        now()->addDay()
    )->plainTextToken;
}

test('sync push create is idempotent by operation id and client uuid', function () {
    $user = User::factory()->create();
    $token = mobileTokenFor($user);
    $operationId = (string) Str::uuid();
    $clientUuid = (string) Str::uuid();

    $payload = [
        'operations' => [[
            'operation_id' => $operationId,
            'entity_type' => 'catatan',
            'action' => 'create',
            'client_uuid' => $clientUuid,
            'payload' => [
                'judul' => 'Catatan offline',
                'isi' => 'Isi dari perangkat offline.',
                'tanggal' => '2026-05-08',
                'status_sampah' => false,
            ],
        ]],
    ];

    $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), $payload)
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'synced')
        ->assertJsonPath('data.operations.0.client_uuid', $clientUuid);

    $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), $payload)
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'synced')
        ->assertJsonPath('data.operations.0.client_uuid', $clientUuid);

    expect(Catatan::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(SyncOperation::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('sync push detects stale server version conflict', function () {
    $user = User::factory()->create();
    $token = mobileTokenFor($user);

    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Versi awal',
        'isi' => 'Isi awal',
        'preview_isi' => 'Isi awal',
        'tanggal' => '2026-05-08',
        'status_sampah' => false,
    ]);
    $baseVersion = (int) $catatan->server_version;
    $catatan->update(['judul' => 'Sudah berubah di server']);

    $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'catatan',
                'action' => 'update',
                'client_uuid' => $catatan->sync_uuid,
                'server_id' => $catatan->id,
                'base_server_version' => $baseVersion,
                'payload' => [
                    'judul' => 'Edit offline lama',
                    'isi' => 'Isi edit offline',
                    'tanggal' => '2026-05-08',
                    'status_sampah' => false,
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.operations.0.status', 'conflict')
        ->assertJsonPath('data.operations.0.server_id', $catatan->id);
});
