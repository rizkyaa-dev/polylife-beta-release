<?php

namespace App\Services\Reminder;

use App\Support\GuestWorkspace;
use Illuminate\Support\Carbon;

class GuestReminderFeedService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Carbon $now, int $limit = 6): array
    {
        $timezone = $now->getTimezone()->getName();
        $nowTz = $now->copy()->setTimezone($timezone);

        $reminders = GuestWorkspace::todolists()
            ->flatMap(fn ($todo) => $todo->reminders->map(function ($reminder) use ($todo) {
                $reminder->todo_title = $todo->nama_item;

                return $reminder;
            }))
            ->map(function ($reminder) use ($timezone) {
                $reminder->normalized_deadline = $this->normalizeDeadline($reminder->waktu_reminder, $timezone);

                return $reminder;
            })
            ->filter(fn ($reminder) => $reminder->aktif && $reminder->normalized_deadline?->gte($nowTz))
            ->sortBy(fn ($reminder) => $reminder->normalized_deadline?->getTimestamp() ?? PHP_INT_MAX)
            ->take($limit);

        return $reminders->map(function ($reminder) use ($nowTz) {
            $deadline = $reminder->normalized_deadline;
            $secondsLeft = (int) max(0, $nowTz->diffInSeconds($deadline, false));

            return [
                'id' => $reminder->id,
                'title' => $reminder->todo_title ?? 'Reminder',
                'waktu_formatted' => $deadline->translatedFormat('l, d F Y H:i'),
                'time_left_text' => $this->formatTimeLeft($secondsLeft),
                'time_diff' => $deadline->diffForHumans($nowTz, false, false, 2),
                'seconds_left' => $secondsLeft,
                'badge_classes' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'dot_classes' => 'bg-indigo-400',
                'blink' => false,
                'edit_url' => '#',
            ];
        })->values()->all();
    }

    private function normalizeDeadline(mixed $rawDeadline, string $timezone): ?Carbon
    {
        if ($rawDeadline instanceof Carbon) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $rawDeadline->format('Y-m-d H:i:s'), $timezone);
        }

        $value = trim((string) $rawDeadline);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        } catch (\Exception) {
            try {
                return Carbon::parse($value, $timezone);
            } catch (\Exception) {
                return null;
            }
        }
    }

    private function formatTimeLeft(int $secondsLeft): string
    {
        if ($secondsLeft <= 0) {
            return 'Segera jatuh tempo';
        }

        $unitsSeconds = [
            'bulan' => 30 * 24 * 3600,
            'minggu' => 7 * 24 * 3600,
            'hari' => 24 * 3600,
            'jam' => 3600,
            'menit' => 60,
            'detik' => 1,
        ];

        $remaining = $secondsLeft;
        $parts = [];

        foreach ($unitsSeconds as $unit => $seconds) {
            $value = intdiv($remaining, $seconds);
            if ($value <= 0 && $parts !== []) {
                continue;
            }

            if ($value > 0) {
                $parts[] = $value . ' ' . $unit;
                $remaining -= $value * $seconds;
            }

            if (count($parts) >= 2) {
                break;
            }
        }

        if ($parts === []) {
            $parts[] = '0 detik';
        }

        return 'Sisa ' . implode(' ', $parts);
    }
}
