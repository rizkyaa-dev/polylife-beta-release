<?php

namespace App\Services\Ai\Actions;

use App\Actions\Reminder\UpdateReminderAction as CanonicalUpdateReminder;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class UpdateReminderAction implements AiWriteAction
{
    public function __construct(
        private readonly CanonicalUpdateReminder $updateReminder,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_reminder';
    }

    public function validatePayload(array $payload): array
    {
        $validated = Validator::make($payload, [
            'reminder_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'reminder_target' => ['required', 'in:todolist,tugas,jadwal,kegiatan'],
            'todolist_id' => ['nullable', 'integer'],
            'tugas_id' => ['nullable', 'integer'],
            'jadwal_id' => ['nullable', 'integer'],
            'kegiatan_id' => ['nullable', 'integer'],
            'waktu_reminder' => ['required', 'date_format:Y-m-d H:i:s'],
            'aktif' => ['required', 'boolean'],
        ])->validate();

        $targetField = $validated['reminder_target'].'_id';
        Validator::make($validated, [$targetField => ['required', 'integer']])->validate();
        if ($validated['aktif']) {
            Validator::make($validated, ['waktu_reminder' => ['after:now']])->validate();
        }

        return $validated;
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $reminder = Reminder::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['reminder_id']);
        $this->freshness->assertUnchanged($reminder, $validated['expected_updated_at'], 'Reminder');
        unset($validated['reminder_id'], $validated['expected_updated_at']);

        return ($this->updateReminder)($reminder, (int) $user->id, $validated, $validated);
    }
}
