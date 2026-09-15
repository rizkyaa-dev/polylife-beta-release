<?php

namespace App\Services\Ai\Actions;

use App\Actions\Todolist\SaveTodolistAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class CreateTodolistAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveTodolistAction $saveTodolist
    ) {}

    public function toolName(): string
    {
        return 'create_todolist';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'nama_item' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'boolean'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        return ($this->saveTodolist)(
            null,
            (int) $user->id,
            $validated,
            (bool) ($validated['status'] ?? false),
            false
        );
    }
}
