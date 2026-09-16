<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Tools\GetUpcomingScheduleTool;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ScheduleAvailabilityService
{
    public function __construct(
        private readonly GetUpcomingScheduleTool $schedules,
        private readonly UserTimeContext $timeContext
    ) {}

    /** @return list<array<string, mixed>> */
    public function conflicts(User $user, CarbonImmutable $start, CarbonImmutable $end, ?int $excludeScheduleId = null): array
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_at' => 'Waktu selesai harus setelah waktu mulai.']);
        }
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages(['end_at' => 'Rentang pemeriksaan maksimal 31 hari.']);
        }

        return collect($this->occurrences($user, $start->startOfDay(), $end->startOfDay()))
            ->reject(fn (array $item): bool => $excludeScheduleId !== null && (int) $item['source_schedule_id'] === $excludeScheduleId)
            ->filter(function (array $item) use ($user, $start, $end): bool {
                [$eventStart, $eventEnd] = $this->intervalForOccurrence($user, $item);

                return $eventStart->lessThan($end) && $eventEnd->greaterThan($start);
            })
            ->values()
            ->all();
    }

    /** @return list<array{start: string, end: string, minutes: int}> */
    public function freeSlots(
        User $user,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $dayStart,
        string $dayEnd,
        int $minimumMinutes
    ): array {
        if ($endDate->lessThan($startDate) || $startDate->diffInDays($endDate) > 13) {
            throw ValidationException::withMessages(['end_date' => 'Rentang slot kosong harus 1-14 hari.']);
        }

        $occurrences = collect($this->occurrences($user, $startDate, $endDate))->groupBy('date');
        $slots = [];
        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            $windowStart = $this->timeContext->parse($user, $date->toDateString().' '.$dayStart);
            $windowEnd = $this->timeContext->parse($user, $date->toDateString().' '.$dayEnd);
            if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                throw ValidationException::withMessages(['day_end' => 'Batas akhir hari harus setelah batas awal.']);
            }

            $busy = collect($occurrences->get($date->toDateString(), []))
                ->map(fn (array $item): array => $this->intervalForOccurrence($user, $item))
                ->filter(fn (array $interval): bool => $interval[0]->lessThan($windowEnd) && $interval[1]->greaterThan($windowStart))
                ->map(fn (array $interval): array => [
                    $interval[0]->max($windowStart),
                    $interval[1]->min($windowEnd),
                ])
                ->sortBy(fn (array $interval): int => $interval[0]->getTimestamp())
                ->values();

            $cursor = $windowStart;
            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($busyStart->diffInMinutes($cursor, true) >= $minimumMinutes && $busyStart->greaterThan($cursor)) {
                    $slots[] = $this->serializeSlot($cursor, $busyStart);
                }
                if ($busyEnd->greaterThan($cursor)) {
                    $cursor = $busyEnd;
                }
            }
            if ($windowEnd->greaterThan($cursor) && $cursor->diffInMinutes($windowEnd, true) >= $minimumMinutes) {
                $slots[] = $this->serializeSlot($cursor, $windowEnd);
            }
        }

        return array_slice($slots, 0, 50);
    }

    /** @return list<array<string, mixed>> */
    private function occurrences(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $result = $this->schedules->execute($user, [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);

        return $result['schedules'] ?? [];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function intervalForOccurrence(User $user, array $item): array
    {
        $date = (string) $item['date'];
        if (empty($item['start_time'])) {
            return [
                $this->timeContext->parse($user, $date)->startOfDay(),
                $this->timeContext->parse($user, $date)->addDay()->startOfDay(),
            ];
        }

        $start = $this->timeContext->parse($user, $date.' '.$item['start_time']);
        $end = ! empty($item['end_time'])
            ? $this->timeContext->parse($user, $date.' '.$item['end_time'])
            : $start->addHour();

        return [$start, $end->lessThanOrEqualTo($start) ? $end->addDay() : $end];
    }

    /** @return array{start: string, end: string, minutes: int} */
    private function serializeSlot(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'minutes' => (int) $start->diffInMinutes($end),
        ];
    }
}
