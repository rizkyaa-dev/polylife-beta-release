<?php

namespace App\Services\Ai\Actions;

use App\Actions\Tugas\SaveTugasAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateTugasAction implements AiWriteAction
{
    public function __construct(private readonly SaveTugasAction $saveTugas) {}

    public function toolName(): string
    {
        return 'create_tugas';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'nama_tugas' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'deadline' => ['required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'status_selesai' => ['required', 'boolean'],
            'matkul_id' => ['nullable', 'integer'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        if (isset($validated['matkul_id'])) {
            Validator::make($validated, [
                'matkul_id' => [Rule::exists('matkuls', 'id')->where('user_id', $user->id)],
            ])->validate();
        }

        return ($this->saveTugas)(null, (int) $user->id, $validated, false);
    }
}
