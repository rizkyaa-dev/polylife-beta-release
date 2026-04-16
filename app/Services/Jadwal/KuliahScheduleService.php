<?php

namespace App\Services\Jadwal;

use App\Models\Jadwal;
use App\Models\Matkul;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KuliahScheduleService
{
    public function appendMatkulDetailsForUser(iterable $jadwals, int $userId, ?Collection $matkuls = null): Collection
    {
        $matkulCollection = $matkuls ?: Matkul::ownedBy($userId)
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        return $this->appendMatkulDetails($jadwals, $matkulCollection);
    }

    public function appendMatkulDetails(iterable $jadwals, Collection $matkuls): Collection
    {
        $matkulMap = $matkuls->keyBy('id');

        foreach ($jadwals as $jadwal) {
            $matkulMeta = $jadwal->matkulIds()
                ->map(fn ($id) => $matkulMap->get((int) $id))
                ->filter();

            $jadwal->matkul_names = $matkulMeta->pluck('nama')->filter()->values()->all();
            $jadwal->primary_matkul = $matkulMeta->first();
            $jadwal->matkul_details = $matkulMeta->values();
        }

        return $matkuls;
    }

    /**
     * @param  iterable<int, Jadwal>  $jadwals
     * @return array<string, Collection<int, Jadwal>>
     */
    public function mapJadwalsByDate(iterable $jadwals, ?Carbon $startLimit = null, ?Carbon $endLimit = null): array
    {
        $map = [];

        foreach ($jadwals as $jadwal) {
            $start = Carbon::parse($jadwal->tanggal_mulai);
            $end = Carbon::parse($jadwal->tanggal_selesai);
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                if (($jadwal->jenis ?? null) === 'kuliah' && $cursor->isWeekend()) {
                    $cursor->addDay();
                    continue;
                }

                if ($startLimit && $cursor->lt($startLimit)) {
                    $cursor->addDay();
                    continue;
                }

                if ($endLimit && $cursor->gt($endLimit)) {
                    $cursor->addDay();
                    continue;
                }

                $key = $cursor->toDateString();
                if (! isset($map[$key])) {
                    $map[$key] = collect();
                }

                $map[$key]->push($jadwal);
                $cursor->addDay();
            }
        }

        return $map;
    }

    /**
     * @param  array<string, Collection<int, Jadwal>>  $map
     * @return array<string, Collection<int, Jadwal>>
     */
    public function deduplicateKuliahByDate(array $map): array
    {
        foreach ($map as $key => $collection) {
            $map[$key] = $this->deduplicateKuliahCollection($collection);
        }

        return $map;
    }

    /**
     * @param  Collection<int, Jadwal>  $jadwals
     * @return Collection<int, Jadwal>
     */
    public function deduplicateKuliahCollection(Collection $jadwals): Collection
    {
        $seen = [];

        return $jadwals->filter(function (Jadwal $jadwal) use (&$seen) {
            if (($jadwal->jenis ?? null) !== 'kuliah') {
                return true;
            }

            $signature = $this->kuliahSignature($jadwal);
            if (isset($seen[$signature])) {
                return false;
            }

            $seen[$signature] = true;

            return true;
        })->values();
    }

    private function kuliahSignature(Jadwal $jadwal): string
    {
        $signatures = collect();

        $matkulIds = $jadwal->matkulIds()
            ->map(fn ($id) => 'id:' . (string) $id)
            ->filter();
        if ($matkulIds->isNotEmpty()) {
            $signatures = $signatures->merge($matkulIds);
        }

        $matkulNames = collect($jadwal->matkul_names ?? [])
            ->map(fn ($name) => 'name:' . Str::lower(trim((string) $name)))
            ->filter();
        if ($matkulNames->isNotEmpty()) {
            $signatures = $signatures->merge($matkulNames);
        }

        $matkulDetails = collect($jadwal->matkul_details ?? [])
            ->map(fn ($matkul) => $this->matkulDetailSignature($matkul))
            ->filter();
        if ($matkulDetails->isNotEmpty()) {
            $signatures = $signatures->merge($matkulDetails);
        }

        if ($signatures->isEmpty()) {
            $signatures = $jadwal->matkulIds()
                ->merge($matkulDetails)
                ->merge($matkulNames)
                ->filter();
        }

        $signature = $signatures
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(';');

        if ($signature === '') {
            $signature = 'generic';
        }

        return 'kuliah|' . $signature;
    }

    private function matkulDetailSignature(mixed $matkul): string
    {
        $name = Str::lower(trim((string) ($matkul->nama ?? '')));
        $kode = Str::lower(trim((string) ($matkul->kode ?? '')));
        $id = isset($matkul->id) ? (string) $matkul->id : '';
        $start = $this->resolveMatkulTime($matkul, 'primaryStartTime', 'jam_mulai');
        $end = $this->resolveMatkulTime($matkul, 'primaryEndTime', 'jam_selesai');

        if ($id === '' && $name === '' && $kode === '' && $start === '' && $end === '') {
            return '';
        }

        return implode('|', array_filter([
            $id !== '' ? 'id:' . $id : null,
            $name !== '' ? 'name:' . $name : null,
            $kode !== '' ? 'kode:' . $kode : null,
            $start !== '' ? 'start:' . $start : null,
            $end !== '' ? 'end:' . $end : null,
        ]));
    }

    private function resolveMatkulTime(mixed $matkul, string $method, string $property): string
    {
        if (method_exists($matkul, $method)) {
            $value = $matkul->{$method}();
        } elseif (isset($matkul->{$property})) {
            $value = $matkul->{$property};
        } else {
            $value = null;
        }

        return $value ? (string) $value : '';
    }
}
