<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) ($this['id'] ?? 0),
            'title' => (string) ($this['title'] ?? 'Reminder'),
            'target_type' => (string) ($this['target_type'] ?? 'reminder'),
            'scheduled_at' => (string) ($this['waktu_iso'] ?? ''),
            'scheduled_label' => (string) ($this['waktu_formatted'] ?? ''),
            'relative_label' => (string) ($this['time_diff'] ?? ''),
            'time_left_text' => (string) ($this['time_left_text'] ?? ''),
            'seconds_left' => max(0, (int) ($this['seconds_left'] ?? 0)),
        ];
    }
}
