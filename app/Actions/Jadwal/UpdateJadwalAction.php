<?php

namespace App\Actions\Jadwal;

use App\Models\Jadwal;

class UpdateJadwalAction
{
    public function __construct(
        private readonly ResolveMatkulListAction $resolveMatkulListAction
    ) {
    }

    public function __invoke(Jadwal $jadwal, array $jadwalData, array $selectedMatkuls, bool $createMatkul, array $matkulFields): Jadwal
    {
        $jadwalData['matkul_id_list'] = ($this->resolveMatkulListAction)(
            (int) $jadwal->user_id,
            $selectedMatkuls,
            $createMatkul,
            $matkulFields
        );

        $jadwal->update($jadwalData);

        return $jadwal;
    }
}
