<?php

namespace App\Services\Reminder;

use App\Models\Reminder;
use App\Models\Todolist;
use Carbon\Carbon;

class TodolistReminderService
{
    public function sync(Todolist $todolist, int $userId, bool $enabled, ?string $date, ?string $time): void
    {
        $existingReminder = $todolist->reminders()->first();

        if ($enabled) {
            $reminderDateTime = Carbon::parse($date . ' ' . ($time ?: '00:00'));

            if ($existingReminder) {
                $existingReminder->update([
                    'waktu_reminder' => $reminderDateTime,
                    'aktif' => true,
                ]);

                return;
            }

            Reminder::query()->create([
                'user_id' => $userId,
                'todolist_id' => $todolist->id,
                'waktu_reminder' => $reminderDateTime,
                'aktif' => true,
            ]);

            return;
        }

        if ($existingReminder) {
            $existingReminder->update(['aktif' => false]);
        }
    }

    /**
     * @return array{text: string, classes: string}
     */
    public function badgeFor(Todolist $todolist): array
    {
        $hasReminder = $todolist->reminders->isNotEmpty();
        $hasActiveReminder = $todolist->reminders->where('aktif', true)->isNotEmpty();

        if (! $hasReminder) {
            return [
                'text' => 'Tanpa reminder',
                'classes' => 'bg-gray-100 text-gray-600',
            ];
        }

        if ($hasActiveReminder) {
            return [
                'text' => 'Reminder aktif',
                'classes' => 'bg-indigo-50 text-indigo-700',
            ];
        }

        return [
            'text' => 'Reminder nonaktif',
            'classes' => 'bg-amber-50 text-amber-700',
        ];
    }
}
