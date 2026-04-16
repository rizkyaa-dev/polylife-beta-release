<?php

namespace App\Queries\Dashboard;

use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingRemindersQuery
{
    /**
     * @return Collection<int, Reminder>
     */
    public function forUser(int $userId, Carbon $now, int $limit = 8): Collection
    {
        return Reminder::query()
            ->with(['todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->where('user_id', $userId)
            ->where('aktif', true)
            ->where('waktu_reminder', '>=', $now)
            ->orderBy('waktu_reminder')
            ->take($limit)
            ->get();
    }
}
