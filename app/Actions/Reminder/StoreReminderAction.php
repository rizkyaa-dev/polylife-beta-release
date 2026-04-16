<?php

namespace App\Actions\Reminder;

use App\Models\Reminder;
use App\Services\Reminder\ReminderPayloadService;

class StoreReminderAction
{
    public function __construct(
        private readonly ReminderPayloadService $reminderPayloadService
    ) {
    }

    public function __invoke(int $userId, array $validated, array $input): Reminder
    {
        return Reminder::query()->create(
            $this->reminderPayloadService->build($userId, $validated, $input)
        );
    }
}
