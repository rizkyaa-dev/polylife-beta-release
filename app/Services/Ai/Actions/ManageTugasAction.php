<?php

namespace App\Services\Ai\Actions;

use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class ManageTugasAction implements AiWriteAction
{
    public function toolName(): string
    {
        return 'manage_tugas';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'tugas_id' => ['required', 'integer'],
            'operation' => ['required', 'in:complete,reopen,rename'],
            'new_name' => ['nullable', 'required_if:operation,rename', 'string', 'max:150'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $task = Tugas::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['tugas_id']);

        match ($validated['operation']) {
            'complete' => $task->update(['status_selesai' => true]),
            'reopen' => $task->update(['status_selesai' => false]),
            'rename' => $task->update(['nama_tugas' => trim($validated['new_name'])]),
        };
        if ($validated['operation'] === 'complete') {
            $task->reminders()->where('aktif', true)->update(['aktif' => false]);
        }

        return $task->fresh();
    }
}
