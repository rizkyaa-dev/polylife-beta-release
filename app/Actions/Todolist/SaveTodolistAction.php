<?php

namespace App\Actions\Todolist;

use App\Models\Todolist;
use App\Services\Reminder\TodolistReminderService;

class SaveTodolistAction
{
    public function __construct(
        private readonly TodolistReminderService $todolistReminderService
    ) {
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(?Todolist $todolist, int $userId, array $validated, bool $status, bool $reminderEnabled): Todolist
    {
        $payload = [
            'user_id' => $userId,
            'nama_item' => trim((string) $validated['nama_item']),
            'status' => $status,
        ];

        $todolist = $todolist
            ? tap($todolist)->update($payload)
            : Todolist::query()->create($payload);

        $this->todolistReminderService->sync(
            $todolist,
            $userId,
            $reminderEnabled,
            $validated['reminder_date'] ?? null,
            $validated['reminder_time'] ?? null
        );

        return $todolist->fresh(['reminders']);
    }
}
