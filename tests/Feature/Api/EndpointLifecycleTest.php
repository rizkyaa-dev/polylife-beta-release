<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\AffiliationBroadcast;
use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->apiUser = User::factory()->create();
    $this->apiToken = $this->apiUser->createToken(
        'endpoint-test', [AuthController::MOBILE_API_ABILITY], now()->addDay()
    )->plainTextToken;
});

dataset('mobile CRUD resources', [
    'catatan' => ['catatan', Catatan::class, [
        'judul' => 'Catatan API', 'isi' => 'Isi privat', 'tanggal' => '2026-10-02',
    ], 'judul', 'Catatan diperbarui'],
    'keuangan' => ['keuangan', Keuangan::class, [
        'jenis' => 'pengeluaran', 'kategori' => 'Makan', 'nominal' => 25000, 'tanggal' => '2026-10-02',
    ], 'kategori', 'Transport'],
    'jadwal' => ['jadwal', Jadwal::class, [
        'title' => 'Agenda API', 'type' => 'personal',
        'start_at' => '2026-10-03 09:00:00', 'end_at' => '2026-10-03 10:00:00',
    ], 'title', 'Agenda diperbarui'],
    'todolist' => ['todolist', Todolist::class, [
        'nama_item' => 'Tugas API', 'status' => false, 'reminder_enabled' => false,
    ], 'nama_item', 'Tugas diperbarui'],
]);

test('mobile resource supports create list update and delete', function (
    string $resource, string $model, array $payload, string $field, string $updatedValue, string $method
) {
    $this->withToken($this->apiToken);
    $created = $this->postJson(route("api.v1.{$resource}.store"), $payload)
        ->assertCreated()->assertJsonPath("data.{$field}", $payload[$field]);
    $id = $created->json('data.id');
    expect($model::findOrFail($id)->user_id)->toBe($this->apiUser->id);

    $this->getJson(route("api.v1.{$resource}.index"))
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);

    if ($resource !== 'todolist') {
        $this->getJson(route("api.v1.{$resource}.show", [$resource => $id]))
            ->assertOk()->assertJsonPath('data.id', $id);
    }

    $payload[$field] = $updatedValue;
    $this->json($method, route("api.v1.{$resource}.update", [$resource => $id]), $payload)
        ->assertOk()->assertJsonPath("data.{$field}", $updatedValue);
    expect($model::findOrFail($id)->getAttribute($field))->toBe($updatedValue);

    $this->deleteJson(route("api.v1.{$resource}.destroy", [$resource => $id]))->assertOk();
    $this->getJson(route("api.v1.{$resource}.index"))->assertOk()->assertJsonPath('meta.total', 0);
    if ($resource === 'catatan') {
        expect($model::findOrFail($id)->status_sampah)->toBeTrue();
    } else {
        expect($model::find($id))->toBeNull();
    }
})->with('mobile CRUD resources')->with(['PUT', 'PATCH']);

test('mobile resource rejects invalid create payload', function (
    string $resource, string $model, array $payload, string $field
) {
    unset($payload[$field]);
    $this->withToken($this->apiToken)->postJson(route("api.v1.{$resource}.store"), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors([$field]);
    expect($model::query()->count())->toBe(0);
})->with('mobile CRUD resources');

test('mobile resource hides another users records and rejects mutations', function (
    string $resource, string $model, array $payload, string $field, string $updatedValue
) {
    $this->withToken($this->apiToken);
    $id = $this->postJson(route("api.v1.{$resource}.store"), $payload)->assertCreated()->json('data.id');
    $other = User::factory()->create();
    $token = $other->createToken('other', [AuthController::MOBILE_API_ABILITY], now()->addDay())->plainTextToken;
    // Sanctum caches the authenticated user between requests in the same test application.
    $this->app['auth']->forgetGuards();
    $this->withToken($token);
    $this->getJson(route("api.v1.{$resource}.index"))->assertOk()->assertJsonPath('meta.total', 0);
    if ($resource !== 'todolist') {
        $this->getJson(route("api.v1.{$resource}.show", [$resource => $id]))->assertNotFound();
    }
    $payload[$field] = $updatedValue;
    $this->patchJson(route("api.v1.{$resource}.update", [$resource => $id]), $payload)->assertNotFound();
    $this->deleteJson(route("api.v1.{$resource}.destroy", [$resource => $id]))->assertNotFound();
    expect($model::findOrFail($id)->getAttribute($field))->not->toBe($updatedValue);
})->with('mobile CRUD resources');

test('catatan can be trashed restored and permanently deleted', function () {
    $this->withToken($this->apiToken);
    $id = $this->postJson(route('api.v1.catatan.store'), [
        'judul' => 'Privat', 'isi' => 'Catatan rahasia', 'tanggal' => '2026-10-02',
    ])->assertCreated()->json('data.id');
    $this->deleteJson(route('api.v1.catatan.destroy', $id))->assertOk();
    $this->getJson(route('api.v1.catatan.trash'))->assertOk()->assertJsonPath('data.0.id', $id);
    $this->patchJson(route('api.v1.catatan.restore', $id))
        ->assertOk()->assertJsonPath('data.status_sampah', false);
    $this->getJson(route('api.v1.catatan.trash'))->assertOk()->assertJsonPath('meta.total', 0);
    $this->deleteJson(route('api.v1.catatan.force-delete', $id))->assertOk();
    $this->assertDatabaseMissing('catatans', ['id' => $id]);
    $this->getJson(route('api.v1.catatan.show', $id))->assertNotFound();
});

test('reminder can be created listed selected as next and deleted', function () {
    $this->withToken($this->apiToken);
    $todo = Todolist::create(['user_id' => $this->apiUser->id, 'nama_item' => 'Belajar', 'status' => false]);
    $this->getJson(route('api.v1.reminder.next'))->assertOk()->assertJsonPath('data', null);
    $id = $this->postJson(route('api.v1.reminder.store'), [
        'reminder_target' => 'todolist', 'todolist_id' => $todo->id,
        'waktu_reminder' => now(config('app.dashboard_timezone'))->addHour()->toDateTimeString(), 'aktif' => true,
    ])->assertCreated()->assertJsonPath('data.title', 'Belajar')
        ->assertJsonPath('data.active', true)->json('data.id');
    $this->getJson(route('api.v1.reminder.index'))
        ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.active_count', 1);
    $this->getJson(route('api.v1.reminder.options'))
        ->assertOk()->assertJsonPath('data.targets.0.options.0.id', $todo->id);
    $this->getJson(route('api.v1.reminder.next'))->assertOk()->assertJsonPath('data.id', $id);
    $this->deleteJson(route('api.v1.reminder.destroy', $id))->assertOk();
    expect(Reminder::find($id))->toBeNull();
});

test('reminder rejects another users target and deletion', function () {
    $other = User::factory()->create();
    $todo = Todolist::create(['user_id' => $other->id, 'nama_item' => 'Privat', 'status' => false]);
    $reminder = Reminder::create([
        'user_id' => $other->id, 'todolist_id' => $todo->id,
        'waktu_reminder' => now()->addHour(), 'aktif' => true,
    ]);
    $this->withToken($this->apiToken);
    $this->postJson(route('api.v1.reminder.store'), [
        'reminder_target' => 'todolist', 'todolist_id' => $todo->id,
        'waktu_reminder' => now()->addHour()->toDateTimeString(), 'aktif' => true,
    ])->assertForbidden();
    $this->deleteJson(route('api.v1.reminder.destroy', $reminder->id))->assertForbidden();
    $this->getJson(route('api.v1.reminder.index'))->assertOk()->assertJsonPath('meta.total', 0);
    expect($reminder->fresh())->not->toBeNull();
});

test('reminder rejects a missing target', function () {
    $this->withToken($this->apiToken)->postJson(route('api.v1.reminder.store'), [
        'reminder_target' => 'todolist', 'waktu_reminder' => now()->addHour()->toDateTimeString(),
    ])->assertUnprocessable();
    expect(Reminder::count())->toBe(0);
});

test('pengumuman lists published content and hides drafts and future publications', function () {
    $attributes = [
        'created_by' => $this->apiUser->id, 'title' => 'Pengumuman', 'body' => 'Isi pengumuman',
        'target_mode' => 'global', 'status' => 'published', 'published_at' => now()->subMinute(),
    ];
    $published = AffiliationBroadcast::create($attributes);
    $draft = AffiliationBroadcast::create(array_replace($attributes, ['status' => 'draft']));
    $future = AffiliationBroadcast::create(array_replace($attributes, ['published_at' => now()->addDay()]));
    $this->withToken($this->apiToken);
    $this->getJson(route('api.v1.pengumuman.index'))
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $published->id);
    $this->getJson(route('api.v1.pengumuman.show', $published->id))
        ->assertOk()->assertJsonPath('data.id', $published->id);
    foreach ([$draft, $future] as $hidden) {
        $this->getJson(route('api.v1.pengumuman.show', $hidden->id))->assertNotFound();
    }
});

test('logout revokes only the current token', function () {
    $this->apiUser->createToken('another-device', [AuthController::MOBILE_API_ABILITY]);
    $this->withToken($this->apiToken)->getJson(route('api.v1.auth.me'))
        ->assertOk()->assertJsonPath('data.id', $this->apiUser->id);
    $this->postJson(route('api.v1.auth.logout'))->assertOk();
    expect($this->apiUser->tokens()->count())->toBe(1);
    $this->app['auth']->forgetGuards();
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

test('logout all revokes every token', function () {
    $this->apiUser->createToken('another-device', [AuthController::MOBILE_API_ABILITY]);
    $this->withToken($this->apiToken)->postJson(route('api.v1.auth.logout-all'))->assertOk();
    expect($this->apiUser->tokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

test('mobile routes reject tokens without the mobile ability', function () {
    $token = $this->apiUser->createToken('wrong-ability', ['unrelated:read'], now()->addDay())->plainTextToken;
    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

test('sync pull returns only the current users changes', function () {
    $own = Catatan::create([
        'user_id' => $this->apiUser->id, 'judul' => 'Catatan sendiri',
        'isi' => 'Isi sendiri', 'tanggal' => '2026-10-02', 'status_sampah' => false,
    ]);
    Catatan::create([
        'user_id' => User::factory()->create()->id, 'judul' => 'Catatan orang lain',
        'isi' => 'Rahasia', 'tanggal' => '2026-10-02', 'status_sampah' => false,
    ]);
    $response = $this->withToken($this->apiToken)->getJson(route('api.v1.sync.pull'))
        ->assertOk()->assertJsonStructure(['data' => ['cursor', 'entities']])
        ->assertJsonCount(1, 'data.entities.catatan')->assertJsonPath('data.entities.catatan.0.id', $own->id);
    $this->getJson(route('api.v1.sync.pull', ['cursor' => $response->json('data.cursor')]))
        ->assertOk()->assertJsonCount(0, 'data.entities.catatan');
});

test('todolist manages its reminder and deletes it with the task', function () {
    $this->withToken($this->apiToken);
    $id = $this->postJson(route('api.v1.todolist.store'), [
        'nama_item' => 'Belajar besok', 'status' => false, 'reminder_enabled' => true,
        'reminder_date' => now()->addDay()->toDateString(), 'reminder_time' => '09:00',
    ])->assertCreated()->assertJsonPath('data.reminder_enabled', true)->json('data.id');
    expect(Reminder::where('todolist_id', $id)->where('aktif', true)->count())->toBe(1);
    $this->deleteJson(route('api.v1.todolist.destroy', $id))->assertOk();
    expect(Reminder::where('todolist_id', $id)->count())->toBe(0);
});

test('jadwal rejects an end time before its start', function () {
    $this->withToken($this->apiToken)->postJson(route('api.v1.jadwal.store'), [
        'title' => 'Rentang salah', 'type' => 'personal',
        'start_at' => '2026-10-03 10:00:00', 'end_at' => '2026-10-03 09:00:00',
    ])->assertUnprocessable()->assertJsonValidationErrors(['end_at']);
    expect(Jadwal::count())->toBe(0);
});

test('every protected mobile endpoint rejects unauthenticated requests', function () {
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->getName() ?? '', 'api.v1.')
            || ! in_array('auth:sanctum', $route->gatherMiddleware(), true)) {
            continue;
        }
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->json($method, $uri)->assertUnauthorized();
            $checked++;
        }
    }
    expect($checked)->toBeGreaterThan(40);
});
