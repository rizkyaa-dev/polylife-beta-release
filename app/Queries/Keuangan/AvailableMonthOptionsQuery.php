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
        $limit = max(1, $limit);
        $monthExpression = $this->monthExpression();

        $options = Keuangan::query()
            ->where('user_id', $userId)
            ->selectRaw($monthExpression.' as month_key')
            ->whereNotNull('tanggal')
            ->distinct()
            ->orderByDesc('month_key')
            ->limit($limit)
            ->pluck('month_key')
            ->map(function (mixed $monthKey): ?Carbon {
                try {
                    return Carbon::createFromFormat('Y-m', (string) $monthKey)->startOfMonth();
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter(fn (?Carbon $date) => $date instanceof Carbon)
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

    private function monthExpression(): string
    {
        return match (Keuangan::query()->getConnection()->getDriverName()) {
            'pgsql' => "TO_CHAR(tanggal, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', tanggal)",
            'sqlsrv' => 'CONVERT(char(7), tanggal, 120)',
            default => "DATE_FORMAT(tanggal, '%Y-%m')",
        };
    }
}
