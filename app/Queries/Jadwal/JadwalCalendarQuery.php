<?php

namespace App\Queries\Jadwal;

use App\Models\Jadwal;
use App\Models\Matkul;
use App\Services\Jadwal\KegiatanCalendarService;
use App\Services\Jadwal\KuliahScheduleService;
use Illuminate\Support\Carbon;

class JadwalCalendarQuery
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService,
        private readonly KegiatanCalendarService $kegiatanCalendarService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(int $userId, Carbon $selectedDate, Carbon $calendarMonth): array
    {
        $startCalendar = $calendarMonth->copy()->startOfMonth()->startOfWeek();
        $endCalendar = $calendarMonth->copy()->endOfMonth()->endOfWeek();

        $jadwals = Jadwal::query()
            ->with('kegiatans')
            ->where('user_id', $userId)
            ->whereDate('tanggal_selesai', '>=', $startCalendar->toDateString())
            ->whereDate('tanggal_mulai', '<=', $endCalendar->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();

        $matkuls = Matkul::ownedBy($userId)
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        $this->kuliahScheduleService->appendMatkulDetails($jadwals, $matkuls);
        $jadwalsByDate = $this->kuliahScheduleService->deduplicateKuliahByDate(
            $this->kuliahScheduleService->mapJadwalsByDate($jadwals)
        );

        $calendarDays = [];
        $iterate = $startCalendar->copy();
        while ($iterate <= $endCalendar) {
            $calendarDays[] = $iterate->copy();
            $iterate->addDay();
        }

        $selectedDateKey = $selectedDate->toDateString();
        $selectedDayEvents = $this->kuliahScheduleService->deduplicateKuliahCollection(
            ($jadwalsByDate[$selectedDateKey] ?? collect())->sortBy('tanggal_mulai')
        );

        return [
            'jadwals' => $jadwals,
            'calendarDays' => $calendarDays,
            'selectedDate' => $selectedDate,
            'calendarMonth' => $calendarMonth,
            'jadwalsByDate' => $jadwalsByDate,
            'selectedDayEvents' => $selectedDayEvents,
            'kegiatanDays' => $this->kegiatanCalendarService->collectKegiatanDays($jadwals),
            'kegiatanByDate' => $this->kegiatanCalendarService->collectKegiatanByDate($jadwals),
            'matkuls' => $matkuls,
        ];
    }
}
