<?php

namespace App\Services\Reminder;

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use Illuminate\Support\Carbon;

class ReminderPayloadService
{
    public function assertOwnership(Reminder $reminder, int $userId): void
    {
        if ((int) $reminder->user_id !== $userId) {
            abort(403, 'Akses ditolak');
        }
    }

    public function build(int $userId, array $validated, array $input, ?Reminder $reminder = null): array
    {
        $targetFields = ['todolist_id', 'tugas_id', 'jadwal_id', 'kegiatan_id'];
        $targetField = ((string) $validated['reminder_target']) . '_id';

        foreach ($targetFields as $field) {
            $value = $input[$field] ?? null;
            $validated[$field] = $field === $targetField ? ($value ?: null) : null;
        }

        if (empty($validated[$targetField])) {
            abort(422, 'Target reminder harus dipilih.');
        }

        $this->authorizeTargetOwnership($targetField, (int) $validated[$targetField], $userId);

        if (! $reminder) {
            $validated['user_id'] = $userId;
        }

        $validated['aktif'] = array_key_exists('aktif', $input);
        $validated['waktu_reminder'] = Carbon::parse($validated['waktu_reminder'])->toDateTimeString();

        unset($validated['reminder_target']);

        return $validated;
    }

    public function resolveReminderTarget(Reminder $reminder): string
    {
        if ($reminder->tugas_id) {
            return 'tugas';
        }

        if ($reminder->jadwal_id) {
            return 'jadwal';
        }

        if ($reminder->kegiatan_id) {
            return 'kegiatan';
        }

        return 'todolist';
    }

    private function authorizeTargetOwnership(string $field, int $id, int $userId): void
    {
        $map = [
            'todolist_id' => Todolist::class,
            'tugas_id' => Tugas::class,
            'jadwal_id' => Jadwal::class,
            'kegiatan_id' => Kegiatan::class,
        ];

        $model = $map[$field] ?? null;
        if (! $model) {
            return;
        }

        $record = $field === 'kegiatan_id'
            ? $model::with('jadwal')->find($id)
            : $model::find($id);
        if (! $record) {
            abort(404);
        }

        $ownerId = isset($record->user_id)
            ? $record->user_id
            : optional($record->jadwal)->user_id;

        if ((int) $ownerId !== $userId) {
            abort(403, 'Akses target tidak diizinkan.');
        }
    }
}
