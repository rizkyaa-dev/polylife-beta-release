<?php

use App\Actions\Todolist\SaveTodolistAction;
use App\Models\Todolist;
use App\Models\User;

test('save todolist action creates and updates reminder state', function () {
    $user = User::factory()->create();
    $action = app(SaveTodolistAction::class);

    $todolist = $action(
        null,
        $user->id,
        [
            'nama_item' => 'Bayar listrik',
            'reminder_date' => '2026-04-10',
            'reminder_time' => '09:30',
        ],
        false,
        true
    );

    $todolist->load('reminders');
    expect($todolist->reminders)->toHaveCount(1);
    expect($todolist->reminders->first()->aktif)->toBeTrue();

    $updated = $action(
        $todolist,
        $user->id,
        [
            'nama_item' => 'Bayar listrik rumah',
        ],
        true,
        false
    );

    $updated->load('reminders');
    expect($updated->nama_item)->toBe('Bayar listrik rumah');
    expect($updated->status)->toBeTrue();
    expect($updated->reminders)->toHaveCount(1);
    expect($updated->reminders->first()->aktif)->toBeFalse();
});
