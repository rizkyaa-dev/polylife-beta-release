<?php

namespace App\Services\Ai\Actions;

use App\Actions\Matkul\UpdateMatkulAction;
use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateCourseAction implements AiWriteAction
{
    public function __construct(
        private readonly UpdateMatkulAction $updateCourse,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_course';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'matkul_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'kode' => ['required', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:150'],
            'kelas' => ['required', 'string', 'max:255'],
            'dosen' => ['required', 'string', 'max:120'],
            'semester' => ['required', 'integer', 'between:1,14'],
            'sks' => ['required', 'integer', 'between:1,6'],
            'hari' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'string', 'max:255'],
            'jam_selesai' => ['required', 'string', 'max:255'],
            'ruangan' => ['required', 'string', 'max:255'],
            'warna_label' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'catatan' => ['present', 'string'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $course = Matkul::query()->ownedBy((int) $user->id)->lockForUpdate()->findOrFail($validated['matkul_id']);
        $this->freshness->assertUnchanged($course, $validated['expected_updated_at'], 'Mata kuliah');
        Validator::make($validated, [
            'kode' => [Rule::unique('matkuls', 'kode')->where('user_id', $user->id)->ignore($course->id)],
        ])->validate();
        unset($validated['matkul_id'], $validated['expected_updated_at']);

        return ($this->updateCourse)($course, $validated);
    }
}
