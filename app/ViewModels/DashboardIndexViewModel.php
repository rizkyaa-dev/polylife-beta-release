<?php

namespace App\ViewModels;

use Illuminate\Support\Collection;

class DashboardIndexViewModel
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $guestMode = (bool) ($this->payload['guestMode'] ?? false);
        $matkuls = $this->asCollection($this->payload['matkuls'] ?? collect());

        return array_merge([
            'guestMode' => $guestMode,
            'jadwalIndexRoute' => $guestMode ? route('guest.jadwal.index') : route('jadwal.index'),
            'todolistIndexRoute' => $guestMode ? route('guest.todolist.index') : route('todolist.index'),
            'todolistToggleEnabled' => ! $guestMode,
            'todolistEditEnabled' => ! $guestMode,
            'keuanganFormAction' => $guestMode ? route('guest.home') : route('workspace.home'),
            'reminderManageRoute' => $guestMode ? null : route('reminder.index'),
            'reminderDataEndpoint' => $guestMode ? null : route('dashboard.reminders.data'),
            'keuanganDataEndpoint' => $guestMode ? null : route('dashboard.keuangan.data'),
            'globalMatkuls' => $matkuls,
            'hasMatkulDayData' => $this->hasMatkulDayDataResolver(),
        ], $this->payload);
    }

    private function asCollection(mixed $value): Collection
    {
        return $value instanceof Collection ? $value : collect($value);
    }

    private function hasMatkulDayDataResolver(): callable
    {
        return function ($matkul): bool {
            if (! $matkul) {
                return false;
            }

            if (method_exists($matkul, 'scheduleDays')) {
                return $matkul->scheduleDays()->isNotEmpty();
            }

            return trim((string) ($matkul->hari ?? '')) !== '';
        };
    }
}
