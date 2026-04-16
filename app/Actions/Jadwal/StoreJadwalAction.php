<?php

namespace App\Actions\Jadwal;

use App\Models\Jadwal;

class StoreJadwalAction
{
    public function __construct(
        private readonly ResolveMatkulListAction $resolveMatkulListAction
    ) {
    }

    public function __invoke(int $userId, array $jadwalData, array $selectedMatkuls, bool $createMatkul, array $matkulFields): Jadwal
    {
        $jadwalData['user_id'] = $userId;
        $jadwalData['matkul_id_list'] = ($this->resolveMatkulListAction)(
            $userId,
            $selectedMatkuls,
            $createMatkul,
            $matkulFields
        );

        return Jadwal::query()->create($jadwalData);
    }
}
