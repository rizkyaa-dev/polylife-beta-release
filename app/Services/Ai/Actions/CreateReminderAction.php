<?php

namespace App\Services\Ai\Actions;

use App\Actions\Reminder\StoreReminderAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class CreateReminderAction implements AiWriteAction
{
    public function __construct(private readonly StoreReminderAction $storeReminder) {}

    public function toolName(): string
    {
        return 'create_reminder';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'reminder_target' => ['required', 'in:todolist,tugas,jadwal,kegiatan'],
            'todolist_id' => ['nullable', 'integer'],
            'tugas_id' => ['nullable', 'integer'],
            'jadwal_id' => ['nullable', 'integer'],
            'kegiatan_id' => ['nullable', 'integer'],
            'waktu_reminder' => ['required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'aktif' => ['required', 'boolean'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        return ($this->storeReminder)((int) $user->id, $validated, $validated);
    }
}
