<?php

namespace App\Actions\Reminder;

use App\Models\Reminder;
use App\Services\Reminder\ReminderPayloadService;

class UpdateReminderAction
{
    public function __construct(
        private readonly ReminderPayloadService $reminderPayloadService
    ) {
    }

    public function __invoke(Reminder $reminder, int $userId, array $validated, array $input): Reminder
    {
        $this->reminderPayloadService->assertOwnership($reminder, $userId);

        $reminder->update(
            $this->reminderPayloadService->build($userId, $validated, $input, $reminder)
        );

        return $reminder;
    }
}
