<?php

namespace App\Services\Ai\Actions;

use App\Actions\Kegiatan\SaveKegiatanAction;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class UpdateKegiatanAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveKegiatanAction $saveKegiatan,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_kegiatan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'kegiatan_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'jadwal_id' => ['required', 'integer'],
            'nama_kegiatan' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'tanggal_deadline' => ['required', 'date_format:Y-m-d'],
            'waktu' => ['required', 'date_format:H:i'],
            'status' => ['required', 'in:belum_dimulai,sedang_berjalan,selesai'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $activity = Kegiatan::query()
            ->whereHas('jadwal', fn ($query) => $query->where('user_id', $user->id))
            ->lockForUpdate()
            ->findOrFail($validated['kegiatan_id']);
        $this->freshness->assertUnchanged($activity, $validated['expected_updated_at'], 'Kegiatan');
        unset($validated['kegiatan_id'], $validated['expected_updated_at']);

        return ($this->saveKegiatan)($activity, (int) $user->id, $validated);
    }
}
