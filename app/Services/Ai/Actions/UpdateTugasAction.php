<?php

namespace App\Services\Ai\Actions;

use App\Actions\Tugas\SaveTugasAction;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateTugasAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveTugasAction $saveTugas,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_tugas';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'tugas_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'nama_tugas' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'deadline' => ['required', 'date_format:Y-m-d H:i:s'],
            'status_selesai' => ['required', 'boolean'],
            'matkul_id' => ['nullable', 'integer'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $task = Tugas::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['tugas_id']);
        $this->freshness->assertUnchanged($task, $validated['expected_updated_at'], 'Tugas');
        if (isset($validated['matkul_id'])) {
            Validator::make($validated, [
                'matkul_id' => [Rule::exists('matkuls', 'id')->where('user_id', $user->id)],
            ])->validate();
        }
        unset($validated['tugas_id'], $validated['expected_updated_at']);

        return ($this->saveTugas)($task, (int) $user->id, $validated, (bool) $validated['status_selesai']);
    }
}
