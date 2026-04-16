<?php

namespace App\Http\Resources\Api;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TodolistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reminders = $this->relationLoaded('reminders') ? $this->reminders : collect();
        $activeReminder = $reminders->firstWhere('aktif', true);
        $fallbackReminder = $activeReminder ?? $reminders->sortBy('waktu_reminder')->first();
        $reminderAt = $this->resolveReminderAt($fallbackReminder);

        return [
            'id' => (int) $this->id,
            'nama_item' => (string) ($this->nama_item ?? ''),
            'status' => (bool) $this->status,
            'reminder_enabled' => $activeReminder !== null,
            'reminder_at' => optional($reminderAt)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function resolveReminderAt($reminder): ?Carbon
    {
        if (! $reminder) {
            return null;
        }

        $timezone = config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
        $rawDatetime = $reminder->getRawOriginal('waktu_reminder');

        if (! $rawDatetime) {
            return $reminder->waktu_reminder?->copy()->setTimezone($timezone);
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $rawDatetime, $timezone);
        } catch (\Throwable $e) {
            return $reminder->waktu_reminder?->copy()->setTimezone($timezone);
        }
    }
}
