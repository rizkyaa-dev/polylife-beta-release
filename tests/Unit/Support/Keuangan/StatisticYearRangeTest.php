<?php

use App\Support\Keuangan\StatisticYearRange;
use Illuminate\Support\Carbon;

test('statistic year range accepts only the configured recent years', function () {
    $range = new StatisticYearRange();
    $now = Carbon::create(2026, 7, 7);

    expect($range->resolve('2026', $now))->toBe(2026);
    expect($range->resolve('2021', $now))->toBe(2021);
    expect($range->resolve('2020', $now))->toBe(2026);
    expect($range->resolve('999999', $now))->toBe(2026);
    expect($range->resolve('not-a-year', $now))->toBe(2026);
    expect($range->options($now))->toBe([2026, 2025, 2024, 2023, 2022, 2021]);
});

test('statistic year range resolves only from available years with data', function () {
    $range = new StatisticYearRange();
    $now = Carbon::create(2026, 7, 7);

    // Matches available year
    expect($range->resolveFromAvailable('2024', [2026, 2024], $now))->toBe(2024);
    expect($range->resolveFromAvailable(2026, [2026, 2024], $now))->toBe(2026);

    // Fallbacks when requested year is not in available years
    expect($range->resolveFromAvailable('2023', [2026, 2024], $now))->toBe(2026); // prefers current year if in available
    expect($range->resolveFromAvailable('2020', [2025, 2024], $now))->toBe(2025); // current year 2026 not available, takes latest (first)
    expect($range->resolveFromAvailable('invalid', [2025, 2024], $now))->toBe(2025);
    expect($range->resolveFromAvailable(null, [2026], $now))->toBe(2026);
});
