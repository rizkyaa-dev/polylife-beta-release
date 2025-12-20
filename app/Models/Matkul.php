<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Matkul extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kode',
        'nama',
        'kelas',
        'dosen',
        'semester',
        'sks',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'ruangan',
        'warna_label',
        'catatan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    protected const DAY_NAME_TO_INDEX = [
        'minggu' => 0,
        'senin' => 1,
        'selasa' => 2,
        'rabu' => 3,
        'kamis' => 4,
        'jumat' => 5,
        'jum\'at' => 5,
        'sabtu' => 6,
    ];

    protected const INDEX_TO_DAY_NAME = [
        0 => 'minggu',
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
    ];

    public function scheduleDays(): Collection
    {
        return $this->splitMultiValue($this->attributes['hari'] ?? $this->hari ?? '');
    }

    public function scheduleStartTimes(): Collection
    {
        return $this->splitMultiValue($this->attributes['jam_mulai'] ?? $this->jam_mulai ?? '');
    }

    public function scheduleEndTimes(): Collection
    {
        return $this->splitMultiValue($this->attributes['jam_selesai'] ?? $this->jam_selesai ?? '');
    }

    public function scheduleRooms(): Collection
    {
        return $this->splitMultiValue($this->attributes['ruangan'] ?? $this->ruangan ?? '');
    }

    public function scheduleEntries(): Collection
    {
        $days = $this->scheduleDays();
        $starts = $this->scheduleStartTimes();
        $ends = $this->scheduleEndTimes();
        $rooms = $this->scheduleRooms();
        $classes = $this->classList();

        $max = $days->count();
        $entries = collect();

        for ($index = 0; $index < $max; $index++) {
            $entries->push([
                'hari' => $days[$index] ?? null,
                'jam_mulai' => $starts[$index] ?? ($starts[$index - 1] ?? $starts->last()),
                'jam_selesai' => $ends[$index] ?? ($ends[$index - 1] ?? $ends->last()),
                'ruangan' => $rooms[$index] ?? ($rooms[$index - 1] ?? $rooms->last()),
                'kelas' => $classes[$index] ?? ($classes[$index - 1] ?? $classes->last()),
            ]);
        }

        return $entries->filter(function ($entry) {
            return $entry['hari'] || $entry['jam_mulai'] || $entry['jam_selesai'] || $entry['ruangan'];
        })->values();
    }

    public function scheduleEntriesForDay(?string $dayName): Collection
    {
        if (!$dayName) {
            return $this->scheduleEntries();
        }

        $needle = Str::lower($dayName);

        return $this->scheduleEntries()
            ->filter(function ($entry) use ($needle) {
                return $entry['hari'] && Str::lower($entry['hari']) === $needle;
            })
            ->values();
    }

    public function firstScheduleEntry(?string $dayName = null): ?array
    {
        return $this->scheduleEntriesForDay($dayName)->first();
    }

    public function matchesDay(?string $dayName): bool
    {
        if (!$dayName) {
            return false;
        }

        return $this->scheduleEntriesForDay($dayName)->isNotEmpty();
    }

    public function primaryDay(): ?string
    {
        return $this->scheduleDays()->first();
    }

    public function primaryStartTime(): ?string
    {
        return $this->scheduleStartTimes()->first();
    }

    public function primaryEndTime(): ?string
    {
        return $this->scheduleEndTimes()->first();
    }

    public function primaryRoom(): ?string
    {
        return $this->scheduleRooms()->first();
    }

    public function classList(): Collection
    {
        return $this->splitMultiValue($this->attributes['kelas'] ?? $this->kelas ?? '');
    }

    public function primaryClass(): ?string
    {
        return $this->classList()->first();
    }

    public function scheduleDayIndexes(): Collection
    {
        return $this->scheduleDays()
            ->map(function ($day) {
                $key = Str::lower($day);
                return self::DAY_NAME_TO_INDEX[$key] ?? null;
            })
            ->filter(fn ($value) => $value !== null)
            ->values();
    }

    public function occursOnWeekday(?int $dayIndex): bool
    {
        if ($dayIndex === null) {
            return false;
        }

        return $this->scheduleDayIndexes()->contains($dayIndex);
    }

    public function firstScheduleEntryByIndex(?int $dayIndex = null): ?array
    {
        if ($dayIndex === null) {
            return $this->firstScheduleEntry();
        }

        $name = self::INDEX_TO_DAY_NAME[$dayIndex] ?? null;
        if (!$name) {
            return $this->firstScheduleEntry();
        }

        return $this->firstScheduleEntry($name);
    }

    protected function splitMultiValue(?string $value): Collection
    {
        return collect(explode(';', (string) $value))
            ->map(fn ($segment) => trim($segment))
            ->filter(fn ($segment) => $segment !== '')
            ->values();
    }
}
