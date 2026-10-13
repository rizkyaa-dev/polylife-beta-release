<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\Catatan;
use App\Models\Keuangan;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

test('sync pull includes web changes made in the cursor second and isolates accounts', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = mobileTokenFor($user);
    $catatan = Catatan::query()->create([
        'user_id' => $user->id,
        'judul' => 'Catatan web',
        'isi' => 'Isi web',
        'tanggal' => now()->toDateString(),
    ]);
    $cursor = $this->withToken($token)
        ->getJson(route('api.v1.sync.pull'))
        ->assertOk()
        ->json('data.cursor');

    $catatan->update(['judul' => 'Perubahan web pada detik yang sama']);
    Keuangan::query()->create([
        'user_id' => $user->id,
        'jenis' => 'pemasukan',
        'kategori' => 'Uang saku',
        'nominal' => 50000,
        'tanggal' => now()->toDateString(),
    ]);
    Catatan::query()->create([
        'user_id' => $other->id,
        'judul' => 'Milik akun lain',
        'isi' => 'Pribadi',
        'tanggal' => now()->toDateString(),
    ]);

    $this->withToken($token)
        ->getJson(route('api.v1.sync.pull', ['cursor' => $cursor, 'replay_boundary' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data.entities.catatan')
        ->assertJsonPath('data.entities.catatan.0.judul', 'Perubahan web pada detik yang sama')
        ->assertJsonCount(1, 'data.entities.keuangan')
        ->assertJsonPath('data.entities.keuangan.0.nominal', 50000);

    $catatan->delete();
    $this->withToken($token)
        ->getJson(route('api.v1.sync.pull', ['cursor' => $cursor, 'replay_boundary' => 1]))
        ->assertOk()
        ->assertJsonPath('data.entities.catatan.0.id', $catatan->id);
    expect($catatan->fresh()->deleted_at)->not->toBeNull();
});

test('web and mobile update the same owned note', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web')
        ->post(route('catatan.store'), [
            'judul' => 'Dibuat di web',
            'isi' => 'Isi awal web',
            'tanggal' => '2026-10-13',
            'show_preview' => true,
        ])
        ->assertRedirect(route('catatan.index'));

    // Mobile uses a separate bearer-token client, without the web session guard.
    Auth::forgetGuards();
    $token = mobileTokenFor($user);
    $record = $this->withToken($token)
        ->getJson(route('api.v1.sync.pull'))
        ->assertOk()
        ->assertJsonPath('data.entities.catatan.0.judul', 'Dibuat di web')
        ->json('data.entities.catatan.0');

    $this->postJson(route('api.v1.sync.push'), [
        'operations' => [[
            'operation_id' => (string) Str::uuid(),
            'entity_type' => 'catatan',
            'action' => 'update',
            'client_uuid' => $record['sync_uuid'],
            'server_id' => $record['id'],
            'base_server_version' => $record['server_version'],
            'payload' => [
                'judul' => 'Diperbarui di mobile',
                'isi' => 'Isi terbaru mobile',
                'tanggal' => '2026-10-13',
                'show_preview' => true,
            ],
        ]],
    ])->assertOk()->assertJsonPath('data.operations.0.status', 'synced');

    $this->actingAs($user, 'web')
        ->get(route('catatan.show', $record['id']))
        ->assertOk()
        ->assertSee('Diperbarui di mobile')
        ->assertSee('Isi terbaru mobile');
});
