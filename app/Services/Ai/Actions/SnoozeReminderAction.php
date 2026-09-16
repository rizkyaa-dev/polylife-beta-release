<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class SnoozeReminderAction implements AiWriteAction
{
    public function __construct(private readonly UpdateReminderAction $updateReminder) {}

    public function toolName(): string
    {
        return 'snooze_reminder';
    }

    public function validatePayload(array $payload): array
    {
        return $this->updateReminder->validatePayload($payload);
    }

    public function execute(User $user, array $payload): Model
    {
        return $this->updateReminder->execute($user, $payload);
    }
}
