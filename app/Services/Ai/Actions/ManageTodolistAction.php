<?php

namespace App\Services\Ai\Actions;

use App\Models\Todolist;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class ManageTodolistAction implements AiWriteAction
{
    public function toolName(): string
    {
        return 'manage_todolist';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'todolist_id' => ['required', 'integer'],
            'operation' => ['required', 'in:complete,reopen,rename'],
            'new_name' => ['nullable', 'required_if:operation,rename', 'string', 'max:150'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $todo = Todolist::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['todolist_id']);

        match ($validated['operation']) {
            'complete' => $todo->update(['status' => true]),
            'reopen' => $todo->update(['status' => false]),
            'rename' => $todo->update(['nama_item' => trim($validated['new_name'])]),
        };
        if ($validated['operation'] === 'complete') {
            $todo->reminders()->where('aktif', true)->update(['aktif' => false]);
        }

        return $todo->fresh();
    }
}
