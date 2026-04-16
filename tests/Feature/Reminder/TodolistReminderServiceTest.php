<?php

use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\User;
use App\Services\Reminder\TodolistReminderService;

test('todolist reminder service creates a new reminder and defaults missing time to midnight', function () {
    $user = User::factory()->create();
    $todo = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Bayar kos',
        'status' => false,
    ]);

    app(TodolistReminderService::class)->sync($todo, $user->id, true, '2026-04-15', null);

    $todo->load('reminders');

    expect($todo->reminders)->toHaveCount(1);
    expect($todo->reminders->first()->aktif)->toBeTrue();
    expect($todo->reminders->first()->waktu_reminder->format('Y-m-d H:i:s'))->toBe('2026-04-15 00:00:00');
});

test('todolist reminder service updates existing reminder instead of creating duplicates', function () {
    $user = User::factory()->create();
    $todo = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Bayar internet',
        'status' => false,
    ]);
    $reminder = Reminder::query()->create([
        'user_id' => $user->id,
        'todolist_id' => $todo->id,
        'waktu_reminder' => '2026-04-10 07:00:00',
        'aktif' => false,
    ]);

    app(TodolistReminderService::class)->sync($todo, $user->id, true, '2026-04-16', '08:45');

    expect(Reminder::query()->where('todolist_id', $todo->id)->count())->toBe(1);

    $reminder->refresh();
    expect($reminder->aktif)->toBeTrue();
    expect($reminder->waktu_reminder->format('Y-m-d H:i:s'))->toBe('2026-04-16 08:45:00');
});

test('todolist reminder service disables existing reminder without deleting it', function () {
    $user = User::factory()->create();
    $todo = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Cek tagihan',
        'status' => false,
    ]);
    $reminder = Reminder::query()->create([
        'user_id' => $user->id,
        'todolist_id' => $todo->id,
        'waktu_reminder' => '2026-04-10 07:00:00',
        'aktif' => true,
    ]);

    app(TodolistReminderService::class)->sync($todo, $user->id, false, null, null);

    expect(Reminder::query()->whereKey($reminder->id)->exists())->toBeTrue();
    expect($reminder->fresh()->aktif)->toBeFalse();
});

test('todolist reminder service returns badge state for empty active and inactive reminder collections', function () {
    $user = User::factory()->create();
    $service = app(TodolistReminderService::class);

    $todoWithoutReminder = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Tanpa reminder',
        'status' => false,
    ]);
    $todoWithoutReminder->setRelation('reminders', collect());

    $todoWithActiveReminder = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Reminder aktif',
        'status' => false,
    ]);
    Reminder::query()->create([
        'user_id' => $user->id,
        'todolist_id' => $todoWithActiveReminder->id,
        'waktu_reminder' => '2026-04-10 07:00:00',
        'aktif' => true,
    ]);
    $todoWithActiveReminder->load('reminders');

    $todoWithInactiveReminder = Todolist::query()->create([
        'user_id' => $user->id,
        'nama_item' => 'Reminder nonaktif',
        'status' => false,
    ]);
    Reminder::query()->create([
        'user_id' => $user->id,
        'todolist_id' => $todoWithInactiveReminder->id,
        'waktu_reminder' => '2026-04-10 07:00:00',
        'aktif' => false,
    ]);
    $todoWithInactiveReminder->load('reminders');

    expect($service->badgeFor($todoWithoutReminder))->toBe([
        'text' => 'Tanpa reminder',
        'classes' => 'bg-gray-100 text-gray-600',
    ]);
    expect($service->badgeFor($todoWithActiveReminder))->toBe([
        'text' => 'Reminder aktif',
        'classes' => 'bg-indigo-50 text-indigo-700',
    ]);
    expect($service->badgeFor($todoWithInactiveReminder))->toBe([
        'text' => 'Reminder nonaktif',
        'classes' => 'bg-amber-50 text-amber-700',
    ]);
});
