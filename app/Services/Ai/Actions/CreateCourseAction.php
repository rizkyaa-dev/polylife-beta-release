<?php

namespace App\Services\Ai\Actions;

use App\Actions\Matkul\StoreMatkulAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateCourseAction implements AiWriteAction
{
    public function __construct(private readonly StoreMatkulAction $storeCourse) {}

    public function toolName(): string
    {
        return 'create_course';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'kode' => ['required', 'string', 'max:20'], 'nama' => ['required', 'string', 'max:150'],
            'kelas' => ['required', 'string', 'max:255'], 'dosen' => ['required', 'string', 'max:120'],
            'semester' => ['required', 'integer', 'between:1,14'], 'sks' => ['required', 'integer', 'between:1,6'],
            'hari' => ['required', Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'])],
            'jam_mulai' => ['required', 'date_format:H:i'], 'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruangan' => ['required', 'string', 'max:255'], 'warna_label' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'catatan' => ['present', 'string'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        Validator::make($validated, [
            'kode' => [Rule::unique('matkuls', 'kode')->where('user_id', $user->id)],
        ])->validate();

        return ($this->storeCourse)((int) $user->id, $validated);
    }
}
