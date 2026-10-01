<?php

namespace App\Services\Ai\Actions;

use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class ManageTugasAction implements AiWriteAction
{
    public function __construct(private readonly ProposalFreshnessGuard $freshness) {}

    public function toolName(): string
    {
        return 'manage_tugas';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'expected_record_hash' => ProposalFreshnessGuard::validationRules(),
            'tugas_id' => ['required', 'integer'],
            'operation' => ['required', 'in:complete,reopen,rename'],
            'new_name' => ['nullable', 'required_if:operation,rename', 'string', 'max:150'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $task = Tugas::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['tugas_id']);
        $this->freshness->assertUnchanged($task, $validated['expected_record_hash'], 'Tugas');

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
