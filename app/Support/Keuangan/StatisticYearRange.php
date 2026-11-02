<?php

namespace App\Support\Keuangan;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class StatisticYearRange
{
    private const YEARS_BACK = 5;

    public function resolve(mixed $rawYear, ?CarbonInterface $now = null): int
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $currentYear = (int) $now->year;
        $minimumYear = $currentYear - self::YEARS_BACK;
        $year = filter_var($rawYear, FILTER_VALIDATE_INT);

        if ($year === false || $year < $minimumYear || $year > $currentYear) {
            return $currentYear;
        }

        return (int) $year;
    }

    /**
     * @param list<int> $availableYears
     */
    public function resolveFromAvailable(mixed $rawYear, array $availableYears, ?CarbonInterface $now = null): int
    {
        $year = filter_var($rawYear, FILTER_VALIDATE_INT);

        if ($year !== false && in_array((int) $year, $availableYears, true)) {
            return (int) $year;
        }

        $currentYear = (int) ($now ? Carbon::instance($now)->year : Carbon::now()->year);
        if (in_array($currentYear, $availableYears, true)) {
            return $currentYear;
        }

        return $availableYears[0] ?? $currentYear;
    }

    /**
     * @return list<int>
     */
    public function options(?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $currentYear = (int) $now->year;

        return range($currentYear, $currentYear - self::YEARS_BACK);
    }
}
