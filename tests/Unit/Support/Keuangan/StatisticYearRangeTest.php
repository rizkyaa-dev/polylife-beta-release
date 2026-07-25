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
