<?php

use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\User;
use App\Services\Dashboard\DashboardReminderFormatter;
use Illuminate\Support\Carbon;

test('dashboard reminder formatter builds payload with title urgency and edit url', function () {
    $service = app(DashboardReminderFormatter::class);
    $now = Carbon::create(2026, 4, 9, 10, 0, 0, 'Asia/Jakarta');

    $user = User::factory()->create();
    $todo = Todolist::create([
        'user_id' => $user->id,
        'nama_item' => 'Bayar UKT',
        'status' => false,
    ]);
    $reminder = Reminder::create([
        'user_id' => $user->id,
        'todolist_id' => $todo->id,
        'waktu_reminder' => '2026-04-09 12:30:00',
        'aktif' => true,
    ]);
    $reminder->load('todolist');

    $payload = $service->prepare([$reminder], $now, 'Asia/Jakarta')->first();

    expect($payload)->not->toBeNull();
    expect($payload['title'])->toBe('Bayar UKT');
    expect((int) $payload['seconds_left'])->toBe(9000);
    expect($payload['blink'])->toBeTrue();
    expect($payload['badge_classes'])->toContain('bg-rose-700');
    expect($payload['edit_url'])->toContain('/workspace/reminder/' . $reminder->id . '/edit');
});
