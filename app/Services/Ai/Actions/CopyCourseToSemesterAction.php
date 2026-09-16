<?php

namespace App\Services\Ai\Actions;

use App\Actions\Matkul\StoreMatkulAction;
use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CopyCourseToSemesterAction implements AiWriteAction
{
    public function __construct(
        private readonly StoreMatkulAction $storeCourse,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'copy_course_to_semester';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'source_matkul_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'kode' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'integer', 'between:1,14'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $source = Matkul::query()->ownedBy((int) $user->id)->lockForUpdate()->findOrFail($validated['source_matkul_id']);
        $this->freshness->assertUnchanged($source, $validated['expected_updated_at'], 'Mata kuliah sumber');
        Validator::make($validated, [
            'kode' => [Rule::unique('matkuls', 'kode')->where('user_id', $user->id)],
        ])->validate();

        return ($this->storeCourse)((int) $user->id, [
            'kode' => $validated['kode'],
            'nama' => $source->nama,
            'kelas' => $source->kelas,
            'dosen' => $source->dosen,
            'semester' => $validated['semester'],
            'sks' => $source->sks,
            'hari' => $source->hari,
            'jam_mulai' => $source->jam_mulai,
            'jam_selesai' => $source->jam_selesai,
            'ruangan' => $source->ruangan,
            'warna_label' => $source->warna_label,
            'catatan' => $source->catatan,
        ]);
    }
}
