<?php

namespace App\Queries\Dashboard;

use App\Models\Jadwal;
use App\Models\Matkul;
use App\Services\Jadwal\KegiatanCalendarService;
use App\Services\Jadwal\KuliahScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TodayScheduleQuery
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService,
        private readonly KegiatanCalendarService $kegiatanCalendarService
    ) {
    }

    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function forUser(int $userId, Carbon $date): array
    {
        $jadwalHariIni = Jadwal::query()
            ->with('kegiatans')
            ->where('user_id', $userId)
            ->whereDate('tanggal_mulai', '<=', $date->toDateString())
            ->whereDate('tanggal_selesai', '>=', $date->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();

        $matkuls = Matkul::query()
            ->where('user_id', $userId)
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        $this->kuliahScheduleService->appendMatkulDetails($jadwalHariIni, $matkuls);
        $jadwalHariIni = $this->kuliahScheduleService->deduplicateKuliahCollection($jadwalHariIni);

        return [
            'jadwalHariIni' => $jadwalHariIni,
            'matkuls' => $matkuls,
            'kegiatanByJadwal' => $this->kegiatanCalendarService->collectByJadwalForDate($jadwalHariIni, $date),
        ];
    }
}
