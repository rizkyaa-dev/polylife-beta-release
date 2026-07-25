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
     * @return list<int>
     */
    public function options(?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $currentYear = (int) $now->year;

        return range($currentYear, $currentYear - self::YEARS_BACK);
    }
}
