<?php

namespace App\Http\Resources\Api;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReminderItemResource extends JsonResource
{
    /**
     * @param  Reminder  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $targetType = $this->targetType();
        $scheduledAt = $this->waktu_reminder;
        $destination = in_array($targetType, ['todolist', 'tugas'], true) ? 'todo' : 'jadwal';

        return [
            'id' => (int) $this->id,
            'title' => $this->targetTitle(),
            'target_type' => $targetType,
            'target_label' => $this->targetLabel($targetType),
            'target_context' => $this->targetContext(),
            'destination' => $destination,
            'active' => (bool) $this->aktif,
            'scheduled_at' => optional($scheduledAt)->toIso8601String(),
            'scheduled_label' => $scheduledAt
                ? Carbon::parse($scheduledAt)->locale('id')->translatedFormat('l, d F Y • H:i')
                : '',
            'sync_uuid' => (string) ($this->sync_uuid ?? ''),
            'server_version' => (int) ($this->server_version ?? 1),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }

    private function targetType(): string
    {
        if ($this->tugas_id) {
            return 'tugas';
        }

        if ($this->jadwal_id) {
            return 'jadwal';
        }

        if ($this->kegiatan_id) {
            return 'kegiatan';
        }

        return 'todolist';
    }

    private function targetLabel(string $type): string
    {
        return match ($type) {
            'todolist' => 'To-Do',
            'tugas' => 'Tugas',
            'jadwal' => 'Jadwal',
            'kegiatan' => 'Kegiatan',
            default => 'Reminder',
        };
    }

    private function targetTitle(): string
    {
        return match ($this->targetType()) {
            'todolist' => (string) optional($this->todolist)->nama_item,
            'tugas' => (string) optional($this->tugas)->nama_tugas,
            'jadwal' => (string) optional($this->jadwal)->title,
            'kegiatan' => (string) optional($this->kegiatan)->nama_kegiatan,
            default => 'Reminder',
        };
    }

    private function targetContext(): string
    {
        return match ($this->targetType()) {
            'todolist' => 'To-Do: '.((string) optional($this->todolist)->nama_item),
            'tugas' => 'Tugas: '.((string) optional($this->tugas)->nama_tugas),
            'jadwal' => 'Jadwal: '.((string) optional($this->jadwal)->title),
            'kegiatan' => 'Kegiatan: '.((string) optional($this->kegiatan)->nama_kegiatan),
            default => 'Reminder',
        };
    }
}
