<?php

namespace App\Services\Ai\Actions;

use App\Actions\Jadwal\UpdateJadwalAction as CanonicalUpdateJadwal;
use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateJadwalAction implements AiWriteAction
{
    public function __construct(
        private readonly CanonicalUpdateJadwal $updateJadwal,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_jadwal';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'jadwal_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'jenis' => ['required', Rule::in(['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya'])],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'semester' => ['nullable', 'integer', 'between:1,14'],
            'catatan_tambahan' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'is_completed' => ['required', 'boolean'],
            'matkul_ids' => ['present', 'array'],
            'matkul_ids.*' => ['integer'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $schedule = Jadwal::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['jadwal_id']);
        $this->freshness->assertUnchanged($schedule, $validated['expected_updated_at'], 'Jadwal');

        $matkulIds = $validated['matkul_ids'];
        unset($validated['jadwal_id'], $validated['expected_updated_at'], $validated['matkul_ids']);

        return ($this->updateJadwal)($schedule, $validated, $matkulIds, false, []);
    }
}
