<?php

namespace App\Services\Ai\Actions;

use App\Actions\Kegiatan\SaveKegiatanAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class CreateKegiatanAction implements AiWriteAction
{
    public function __construct(private readonly SaveKegiatanAction $saveKegiatan) {}

    public function toolName(): string
    {
        return 'create_kegiatan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'jadwal_id' => ['required', 'integer'], 'nama_kegiatan' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:100'], 'tanggal_deadline' => ['required', 'date'],
            'waktu' => ['required', 'date_format:H:i'], 'status' => ['required', 'in:belum_dimulai,sedang_berjalan,selesai'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        return ($this->saveKegiatan)(null, (int) $user->id, $this->validatePayload($payload));
    }
}
