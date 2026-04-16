<?php

namespace App\Queries\Jadwal;

use App\Services\Jadwal\KegiatanCalendarService;
use App\Services\Jadwal\KuliahScheduleService;
use App\Support\GuestWorkspace;
use Illuminate\Support\Carbon;

class GuestJadwalCalendarQuery
{
    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService,
        private readonly KegiatanCalendarService $kegiatanCalendarService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forDate(Carbon $selectedDate, Carbon $calendarMonth): array
    {
        $startCalendar = $calendarMonth->copy()->startOfMonth()->startOfWeek();
        $endCalendar = $calendarMonth->copy()->endOfMonth()->endOfWeek();

        $jadwals = GuestWorkspace::jadwals();
        $matkuls = GuestWorkspace::matkuls();

        $this->kuliahScheduleService->appendMatkulDetails($jadwals, $matkuls);
        $jadwalsByDate = $this->kuliahScheduleService->deduplicateKuliahByDate(
            $this->kuliahScheduleService->mapJadwalsByDate($jadwals, $startCalendar, $endCalendar)
        );

        $calendarDays = [];
        $iterate = $startCalendar->copy();
        while ($iterate <= $endCalendar) {
            $calendarDays[] = $iterate->copy();
            $iterate->addDay();
        }

        $selectedDayEvents = $this->kuliahScheduleService->deduplicateKuliahCollection(
            ($jadwalsByDate[$selectedDate->toDateString()] ?? collect())->sortBy('tanggal_mulai')
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
            'guestMode' => true,
        ];
    }
}
