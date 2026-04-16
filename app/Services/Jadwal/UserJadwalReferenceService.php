<?php

namespace App\Services\Jadwal;

use App\Models\Jadwal;

class UserJadwalReferenceService
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService
    ) {
    }

    public function forUser(int $userId)
    {
        $jadwals = Jadwal::query()
            ->where('user_id', $userId)
            ->orderBy('tanggal_mulai')
            ->get();

        $this->kuliahScheduleService->appendMatkulDetailsForUser($jadwals, $userId);

        return $jadwals;
    }
}
