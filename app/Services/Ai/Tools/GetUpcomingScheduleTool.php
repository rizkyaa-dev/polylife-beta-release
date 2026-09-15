<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Jadwal\KuliahScheduleService;
use Illuminate\Support\Carbon;
use Throwable;

class GetUpcomingScheduleTool implements AiToolInterface
{
    private const MAX_RANGE_DAYS = 31;

    private const MAX_SOURCE_SCHEDULES = 50;

    private const MAX_OCCURRENCES = 100;

    private const DAY_NAMES = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public function __construct(
        private readonly KuliahScheduleService $kuliahScheduleService
    ) {}

    public function name(): string
    {
        return 'get_upcoming_schedule';
    }

    public function description(): string
    {
        return 'Mengambil kejadian jadwal kuliah, agenda, atau kegiatan penting milik pengguna per tanggal dalam rentang maksimum 31 hari.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'start_date' => [
                        'type' => 'string',
                        'description' => 'Tanggal awal pencarian (format: YYYY-MM-DD)',
                    ],
                    'end_date' => [
                        'type' => 'string',
                        'description' => 'Tanggal akhir pencarian, inklusif dan maksimal 31 hari dari tanggal awal (format: YYYY-MM-DD)',
                    ],
                ],
                'required' => ['start_date', 'end_date'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $startDate = $this->parseDate($arguments['start_date'] ?? null);
        $endDate = $this->parseDate($arguments['end_date'] ?? null);

        if (! $startDate || ! $endDate) {
            return $this->invalidArguments('Tanggal wajib menggunakan format YYYY-MM-DD yang valid.');
        }

        if ($endDate->lt($startDate)) {
            return $this->invalidArguments('Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        if ($startDate->diffInDays($endDate) >= self::MAX_RANGE_DAYS) {
            return $this->invalidArguments('Rentang pencarian maksimal 31 hari, termasuk tanggal awal dan akhir.');
        }

        $sourceSchedules = Jadwal::query()
            ->where('user_id', $user->id)
            ->whereDate('tanggal_mulai', '<=', $endDate->toDateString())
            ->whereDate('tanggal_selesai', '>=', $startDate->toDateString())
            ->orderBy('tanggal_mulai')
            ->orderBy('start_time')
            ->orderBy('id')
            ->limit(self::MAX_SOURCE_SCHEDULES + 1)
            ->get([
                'id',
                'matkul_id_list',
                'title',
                'jenis',
                'tanggal_mulai',
                'tanggal_selesai',
                'start_time',
                'end_time',
                'location',
                'catatan_tambahan',
            ]);

        $sourceLimitReached = $sourceSchedules->count() > self::MAX_SOURCE_SCHEDULES;
        $sourceSchedules = $sourceSchedules->take(self::MAX_SOURCE_SCHEDULES);
        $this->kuliahScheduleService->appendMatkulDetailsForUser($sourceSchedules, $user->id);

        $scheduleMap = $this->kuliahScheduleService->deduplicateKuliahByDate(
            $this->kuliahScheduleService->mapJadwalsByDate(
                $sourceSchedules,
                $startDate,
                $endDate,
                $user->offDays()
            )
        );

        $occurrences = collect();
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            foreach ($scheduleMap[$cursor->toDateString()] ?? collect() as $schedule) {
                $occurrences->push(...$this->occurrencesForDate($schedule, $cursor, $user));
            }
            $cursor->addDay();
        }

        $occurrences = $occurrences
            ->sortBy(fn (array $occurrence) => $occurrence['date'].' '.($occurrence['start_time'] ?? '99:99').' '.($occurrence['title'] ?? ''))
            ->values();
        $truncated = $sourceLimitReached || $occurrences->count() > self::MAX_OCCURRENCES;

        return [
            'status' => 'success',
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'inclusive' => true,
            ],
            'total' => $occurrences->count(),
            'returned' => min($occurrences->count(), self::MAX_OCCURRENCES),
            'truncated' => $truncated,
            'schedules' => $occurrences->take(self::MAX_OCCURRENCES)->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function occurrencesForDate(Jadwal $schedule, Carbon $date, User $user): array
    {
        if ($schedule->jenis !== 'kuliah') {
            return [$this->serializeOccurrence($schedule, $date)];
        }

        if ($user->isOffDay($date)) {
            return [];
        }

        $courses = collect($schedule->matkul_details ?? []);
        if ($courses->isEmpty()) {
            return $this->hasUsefulLegacyDetails($schedule)
                ? [$this->serializeOccurrence($schedule, $date)]
                : [];
        }

        return $courses
            ->filter(function ($course) use ($date): bool {
                $hasDayData = $course->scheduleDays()->isNotEmpty();

                return ! $hasDayData || $course->occursOnWeekday($date->dayOfWeek);
            })
            ->map(function ($course) use ($schedule, $date): array {
                $slot = $course->firstScheduleEntryByIndex($date->dayOfWeek)
                    ?? $course->firstScheduleEntry();

                return $this->serializeOccurrence($schedule, $date, [
                    'title' => $course->nama ?: $schedule->title,
                    'course_code' => $course->kode ?: null,
                    'class' => $slot['kelas'] ?? $course->primaryClass(),
                    'start_time' => $slot['jam_mulai'] ?? $schedule->start_time,
                    'end_time' => $slot['jam_selesai'] ?? $schedule->end_time,
                    'location' => $slot['ruangan'] ?? $schedule->location,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serializeOccurrence(Jadwal $schedule, Carbon $date, array $overrides = []): array
    {
        return [
            'date' => $date->toDateString(),
            'day' => self::DAY_NAMES[$date->dayOfWeek],
            'type' => $schedule->jenis,
            'title' => $this->nullableString($overrides['title'] ?? $schedule->title),
            'course_code' => $this->nullableString($overrides['course_code'] ?? null),
            'class' => $this->nullableString($overrides['class'] ?? null),
            'start_time' => $this->nullableString($overrides['start_time'] ?? $schedule->start_time),
            'end_time' => $this->nullableString($overrides['end_time'] ?? $schedule->end_time),
            'location' => $this->nullableString($overrides['location'] ?? $schedule->location),
            'notes' => $this->nullableString($schedule->catatan_tambahan),
            'source_schedule_id' => $schedule->id,
        ];
    }

    private function hasUsefulLegacyDetails(Jadwal $schedule): bool
    {
        return collect([$schedule->title, $schedule->start_time, $schedule->end_time, $schedule->location])
            ->contains(fn ($value) => $this->nullableString($value) !== null);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();

            return $date->toDateString() === $value ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidArguments(string $message): array
    {
        return [
            'status' => 'invalid_arguments',
            'message' => $message,
            'total' => 0,
            'returned' => 0,
            'truncated' => false,
            'schedules' => [],
        ];
    }
}
