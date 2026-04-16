<?php

namespace App\Services\Jadwal;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class KegiatanCalendarService
{
    /**
     * @param  iterable<int, mixed>  $jadwals
     * @return array<string, bool>
     */
    public function collectKegiatanDays(iterable $jadwals): array
    {
        $kegiatanDays = [];

        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $this->resolveKegiatanDate($kegiatan);
                if (! $tanggal) {
                    continue;
                }

                $kegiatanDays[$tanggal] = true;
            }
        }

        return $kegiatanDays;
    }

    /**
     * @param  iterable<int, mixed>  $jadwals
     * @return array<string, Collection<int, mixed>>
     */
    public function collectKegiatanByDate(iterable $jadwals): array
    {
        $map = [];

        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $this->resolveKegiatanDate($kegiatan);
                if (! $tanggal) {
                    continue;
                }

                if (! isset($map[$tanggal])) {
                    $map[$tanggal] = collect();
                }

                $map[$tanggal]->push($kegiatan);
            }
        }

        return $map;
    }

    /**
     * @param  iterable<int, mixed>  $jadwals
     * @return Collection<int, Collection<int, mixed>>
     */
    public function collectByJadwalForDate(iterable $jadwals, Carbon $date): Collection
    {
        $map = collect();

        foreach ($jadwals as $jadwal) {
            $filtered = $jadwal->kegiatans->filter(function ($kegiatan) use ($date) {
                return $this->resolveKegiatanDate($kegiatan) === $date->toDateString();
            });

            if ($filtered->isNotEmpty()) {
                $map[$jadwal->id] = $filtered->values();
            }
        }

        return $map;
    }

    private function resolveKegiatanDate(mixed $kegiatan): ?string
    {
        if (! empty($kegiatan->tanggal_deadline)) {
            return Carbon::parse($kegiatan->tanggal_deadline)->toDateString();
        }

        if (! empty($kegiatan->waktu)) {
            return Carbon::parse($kegiatan->waktu)->toDateString();
        }

        return null;
    }
}
