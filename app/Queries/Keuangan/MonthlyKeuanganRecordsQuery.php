<?php

namespace App\Queries\Keuangan;

use App\Models\Keuangan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyKeuanganRecordsQuery
{
    /**
     * @return Collection<int, Keuangan>
     */
    public function forUser(int $userId, Carbon $selectedMonth): Collection
    {
        return Keuangan::query()
            ->where('user_id', $userId)
            ->whereBetween('tanggal', [
                $selectedMonth->copy()->startOfMonth()->toDateString(),
                $selectedMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->get();
    }
}
