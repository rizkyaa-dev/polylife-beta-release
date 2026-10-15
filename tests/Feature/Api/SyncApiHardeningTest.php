<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\Catatan;
use App\Models\Keuangan;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Str;

function mobileTokenForHardening(User $user): string
{
    return $user->createToken(
        'flutter-sync-hardening-test',
        [AuthController::MOBILE_API_ABILITY],
        now()->addDay()
    )->plainTextToken;
}

test('sync push enforces batch size limit of at most 50 operations', function () {
    $user = User::factory()->create();
    $token = mobileTokenForHardening($user);

    // Create 51 operations to exceed MAX_PUSH_OPERATIONS (50)
    $operations = [];
    for ($i = 1; $i <= 51; $i++) {
        $operations[] = [
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'catatan',
            'action' => 'create',
            'client_uuid' => (string) Str::uuid(),
            'payload' => [
                'judul' => "Catatan {$i}",
                'isi' => "Isi {$i}",
                'tanggal' => '2026-05-01',
            ],
        ];
    }

    $response = $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => $operations,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['operations']);
});

test('sync push prevents cross tenant modification and deletion of records', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();
    $attackerToken = mobileTokenForHardening($attacker);

    $victimCatatan = Catatan::query()->create([
        'user_id' => $victim->id,
        'judul' => 'Rahasia Korban',
        'isi' => 'Konten rahasia',
        'tanggal' => '2026-05-01',
        'status_sampah' => false,
    ]);

    // Attacker attempts to update victim's note by server_id
    $updateResponse = $this->withToken($attackerToken)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'catatan',
                'action' => 'update',
                'server_id' => $victimCatatan->id,
                'payload' => [
                    'judul' => 'Telah Diretas',
                    'isi' => 'Konten diubah',
                    'tanggal' => '2026-05-01',
                ],
            ]],
        ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.operations.0.status', 'not_found')
        ->assertJsonPath('data.operations.0.error.message', 'Record target tidak ditemukan.');

    // Verify victim's note remains unaltered
    $victimCatatan->refresh();
    expect($victimCatatan->judul)->toBe('Rahasia Korban');
    expect($victimCatatan->isi)->toBe('Konten rahasia');

    // Attacker attempts to delete victim's note by client_uuid
    $deleteResponse = $this->withToken($attackerToken)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'catatan',
                'action' => 'delete',
                'client_uuid' => $victimCatatan->sync_uuid,
            ]],
        ]);

    $deleteResponse->assertOk()
        ->assertJsonPath('data.operations.0.status', 'not_found');

    expect($victimCatatan->fresh()->deleted_at)->toBeNull();
});

test('sync push handles duplicate operation id in the same batch idempotently', function () {
    $user = User::factory()->create();
    $token = mobileTokenForHardening($user);
    $operationId = (string) Str::uuid();
    $clientUuid = (string) Str::uuid();

    $response = $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [
                [
                    'operation_id' => $operationId,
                    'entity_type' => 'keuangan',
                    'action' => 'create',
                    'client_uuid' => $clientUuid,
                    'payload' => [
                        'jenis' => 'pemasukan',
                        'kategori' => 'Beasiswa',
                        'nominal' => 1500000,
                        'tanggal' => '2026-05-10',
                    ],
                ],
                [
                    'operation_id' => $operationId, // Duplicate within same request payload
                    'entity_type' => 'keuangan',
                    'action' => 'create',
                    'client_uuid' => $clientUuid,
                    'payload' => [
                        'jenis' => 'pemasukan',
                        'kategori' => 'Beasiswa',
                        'nominal' => 1500000,
                        'tanggal' => '2026-05-10',
                    ],
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('data.operations.0.status'))->toBe('synced');
    expect($response->json('data.operations.1.status'))->toBe('synced');

    // Ensure database contains exactly ONE transaction record and ONE sync operation log
    expect(Keuangan::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(SyncOperation::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('sync push restores soft deleted entity deterministically', function () {
    $user = User::factory()->create();
    $token = mobileTokenForHardening($user);

    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Catatan Dihapus',
        'isi' => 'Isi catatan',
        'tanggal' => '2026-05-01',
    ]);
    $catatan->delete();
    expect($catatan->fresh()->trashed())->toBeTrue();

    $restoreResponse = $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'catatan',
                'action' => 'restore',
                'server_id' => $catatan->id,
            ]],
        ]);

    $restoreResponse->assertOk()
        ->assertJsonPath('data.operations.0.status', 'synced');

    expect($catatan->fresh()->trashed())->toBeFalse();
    expect($catatan->fresh()->deleted_at)->toBeNull();
});

test('sync push rejects invalid entity types or unknown actions', function () {
    $user = User::factory()->create();
    $token = mobileTokenForHardening($user);

    $response = $this->withToken($token)
        ->postJson(route('api.v1.sync.push'), [
            'operations' => [[
                'operation_id' => (string) Str::uuid(),
                'entity_type' => 'users', // Invalid entity type
                'action' => 'drop_table', // Invalid action
            ]],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['operations.0.entity_type', 'operations.0.action']);
});
