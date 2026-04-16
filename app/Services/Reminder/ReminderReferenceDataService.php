<?php

namespace App\Services\Reminder;

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Todolist;
use App\Models\Tugas;

class ReminderReferenceDataService
{
    public function forUser(int $userId): array
    {
        return [
            'todolists' => Todolist::query()->where('user_id', $userId)->orderBy('nama_item')->get(),
            'tugasList' => Tugas::query()->where('user_id', $userId)->orderBy('deadline')->get(),
            'jadwals' => Jadwal::query()->where('user_id', $userId)->orderBy('tanggal_mulai')->get(),
            'kegiatans' => Kegiatan::query()
                ->with('jadwal')
                ->whereHas('jadwal', fn ($query) => $query->where('user_id', $userId))
                ->orderBy('tanggal_deadline')
                ->get(),
        ];
    }
}
