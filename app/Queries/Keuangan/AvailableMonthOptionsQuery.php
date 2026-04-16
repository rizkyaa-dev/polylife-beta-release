<?php

namespace App\Queries\Keuangan;

use App\Models\Keuangan;
use Illuminate\Support\Carbon;

class AvailableMonthOptionsQuery
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function forUser(int $userId, Carbon $selectedMonth, Carbon $defaultMonth, int $limit = 12): array
    {
        $options = Keuangan::query()
            ->where('user_id', $userId)
            ->orderByDesc('tanggal')
            ->get(['tanggal'])
            ->map(fn ($row) => Carbon::parse($row->tanggal)->startOfMonth())
            ->unique(fn (Carbon $date) => $date->format('Y-m'))
            ->take($limit)
            ->values()
            ->map(fn (Carbon $date) => [
                'value' => $date->format('Y-m'),
                'label' => $date->translatedFormat('F Y'),
            ]);

        if ($options->isEmpty()) {
            $options->push([
                'value' => $defaultMonth->format('Y-m'),
                'label' => $defaultMonth->translatedFormat('F Y'),
            ]);
        }

        if (! $options->contains(fn (array $option) => $option['value'] === $selectedMonth->format('Y-m'))) {
            $options->push([
                'value' => $selectedMonth->format('Y-m'),
                'label' => $selectedMonth->translatedFormat('F Y'),
            ]);
        }

        return $options
            ->unique('value')
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
