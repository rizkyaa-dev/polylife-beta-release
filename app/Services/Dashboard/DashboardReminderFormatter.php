<?php

namespace App\Services\Dashboard;

use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardReminderFormatter
{
    public function prepare(iterable $reminders, Carbon $now, string $timezone): Collection
    {
        return collect($reminders)
            ->map(fn ($reminder) => $this->formatReminderData($reminder, $now, $timezone))
            ->filter()
            ->values();
    }

    private function resolveReminderTitle(Reminder $reminder): string
    {
        if ($reminder->todolist_id) {
            return optional($reminder->todolist)->nama_item ?: 'Todolist #' . $reminder->todolist_id;
        }

        if ($reminder->tugas_id) {
            return optional($reminder->tugas)->nama_tugas ?: 'Tugas #' . $reminder->tugas_id;
        }

        if ($reminder->kegiatan_id) {
            return optional($reminder->kegiatan)->nama_kegiatan ?: 'Kegiatan #' . $reminder->kegiatan_id;
        }

        if ($reminder->jadwal_id) {
            $jadwal = $reminder->jadwal;
            if ($jadwal) {
                return $jadwal->catatan_tambahan
                    ?: $jadwal->jenis
                    ?: 'Jadwal #' . $jadwal->id;
            }

            return 'Jadwal #' . $reminder->jadwal_id;
        }

        return optional($reminder->todolist)->nama_item
            ?? optional($reminder->tugas)->nama_tugas
            ?? optional($reminder->kegiatan)->nama_kegiatan
            ?? optional($reminder->jadwal)->jenis
            ?? 'Reminder';
    }

    private function resolveReminderTargetType(Reminder $reminder): string
    {
        if ($reminder->todolist_id) {
            return 'todolist';
        }

        if ($reminder->tugas_id) {
            return 'tugas';
        }

        if ($reminder->kegiatan_id) {
            return 'kegiatan';
        }

        if ($reminder->jadwal_id) {
            return 'jadwal';
        }

        return 'reminder';
    }

    private function formatReminderData(Reminder $reminder, Carbon $now, string $timezone): ?array
    {
        $rawDatetime = $reminder->getRawOriginal('waktu_reminder');
        if (! $rawDatetime) {
            return null;
        }

        try {
            $deadline = Carbon::createFromFormat('Y-m-d H:i:s', $rawDatetime, $timezone);
        } catch (\Exception $e) {
            return null;
        }

        $nowTz = $now->copy()->setTimezone($timezone);

        if ($deadline->lt($nowTz)) {
            return null;
        }

        $secondsLeft = max(0, $nowTz->diffInSeconds($deadline, false));
        [$badgeClasses, $dotClasses, $blink] = $this->reminderUrgencyClasses($secondsLeft);
        Carbon::setLocale('id');
        $deadlineLocalized = $deadline->copy()->locale('id');

        return [
            'id' => $reminder->id,
            'title' => $this->resolveReminderTitle($reminder),
            'target_type' => $this->resolveReminderTargetType($reminder),
            'waktu_iso' => $deadline->toIso8601String(),
            'waktu_formatted' => $deadlineLocalized->translatedFormat('l, d F Y H:i'),
            'time_diff' => $deadlineLocalized->diffForHumans($nowTz, false, false, 2),
            'time_left_text' => $this->formatTimeLeft($secondsLeft),
            'seconds_left' => $secondsLeft,
            'badge_classes' => $badgeClasses,
            'dot_classes' => $dotClasses,
            'blink' => $blink,
            'edit_url' => route('reminder.edit', $reminder->id),
        ];
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

        $unitOrder = $this->timeLeftUnitSet($secondsLeft);
        $remaining = $secondsLeft;
        $parts = [];

        foreach ($unitOrder as $index => $unit) {
            $seconds = $unitsSeconds[$unit];
            $value = intdiv($remaining, $seconds);
            if ($value === 0 && $index === 0) {
                continue;
            }

            $parts[] = $value . ' ' . $unit;
            $remaining -= $value * $seconds;
        }

        if ($parts === []) {
            $parts[] = '0 ' . end($unitOrder);
        }

        return 'Sisa ' . implode(' ', $parts);
    }

    /**
     * @return array<int, string>
     */
    private function timeLeftUnitSet(int $secondsLeft): array
    {
        $month = 30 * 24 * 3600;
        $week = 7 * 24 * 3600;
        $day = 24 * 3600;

        if ($secondsLeft >= $month) {
            return ['bulan', 'minggu', 'hari'];
        }

        if ($secondsLeft >= $week) {
            return ['minggu', 'hari', 'jam'];
        }

        if ($secondsLeft >= $day) {
            return ['hari', 'jam', 'menit'];
        }

        return ['jam', 'menit', 'detik'];
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function reminderUrgencyClasses(int $secondsLeft): array
    {
        $oneDay = 24 * 3600;
        $oneWeek = 7 * $oneDay;
        $threeHours = 3 * 3600;

        if ($secondsLeft >= $oneWeek) {
            return [
                'bg-green-50 text-green-700 border border-green-100',
                'bg-green-400',
                false,
            ];
        }

        if ($secondsLeft >= $oneDay) {
            return [
                'bg-amber-50 text-amber-700 border border-amber-200',
                'bg-amber-400',
                false,
            ];
        }

        if ($secondsLeft >= $threeHours) {
            return [
                'bg-rose-50 text-rose-700 border border-rose-200',
                'bg-rose-400',
                false,
            ];
        }

        return [
            'bg-rose-700 text-white border border-black dark:border-white',
            'reminder-dot-critical',
            true,
        ];
    }
}
