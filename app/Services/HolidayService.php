<?php

namespace App\Services;

use App\Models\NationalHoliday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HolidayService
{
    private const API_URL = 'https://libur.deno.dev/api';

    private const CACHE_KEY_PREFIX = 'national_holidays_';

    /**
     * Get all holidays for a specific year (local DB + API fallback).
     */
    public function getHolidays(int $year): array
    {
        return Cache::remember(self::CACHE_KEY_PREFIX.$year, now()->addDay(), function () use ($year) {
            // Priority 1: Local database
            $localHolidays = NationalHoliday::forYear($year)
                ->orderBy('date')
                ->get()
                ->map(fn ($h) => [
                    'date' => $h->date->toDateString(),
                    'name' => $h->name,
                    'is_cuti_bersama' => $h->is_cuti_bersama,
                ])
                ->all();

            if (! empty($localHolidays)) {
                return $localHolidays;
            }

            // Priority 2: External API fallback
            try {
                $response = Http::timeout(10)->get(self::API_URL, [
                    'year' => $year,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && ! empty($data)) {
                        return $data;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('HolidayService: API fetch failed for year '.$year.': '.$e->getMessage());
            }

            return [];
        });
    }

    /**
     * Check if a date is a national holiday.
     */
    public function isHoliday(Carbon $date): bool
    {
        $holidays = $this->getHolidays($date->year);
        $dateString = $date->toDateString();

        foreach ($holidays as $holiday) {
            if (($holiday['date'] ?? '') === $dateString) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the name of the holiday for a specific date.
     */
    public function getHolidayName(Carbon $date): ?string
    {
        $holidays = $this->getHolidays($date->year);
        $dateString = $date->toDateString();

        foreach ($holidays as $holiday) {
            if (($holiday['date'] ?? '') === $dateString) {
                return $holiday['name'] ?? 'Hari Libur Nasional';
            }
        }

        return null;
    }
}
