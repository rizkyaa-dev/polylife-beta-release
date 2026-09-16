<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\ScheduleAvailabilityService;
use App\Services\Ai\UserTimeContext;
use Illuminate\Validation\ValidationException;

final class FindFreeTimeSlotsTool implements AiToolInterface
{
    public function __construct(
        private readonly ScheduleAvailabilityService $availability,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'find_free_time_slots';
    }

    public function description(): string
    {
        return 'Mencari slot waktu kosong 1-14 hari berdasarkan jadwal pengguna. Tidak membuat jadwal.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, maksimal 14 hari inklusif'],
                'minimum_minutes' => ['type' => 'integer', 'description' => 'Durasi minimum 15-480 menit'],
                'day_start' => ['type' => 'string', 'description' => 'Batas awal harian HH:MM, default 08:00'],
                'day_end' => ['type' => 'string', 'description' => 'Batas akhir harian HH:MM, default 21:00'],
            ], 'required' => ['start_date', 'end_date', 'minimum_minutes'],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $start = $this->timeContext->parse($user, $arguments['start_date'] ?? '')->startOfDay();
        $end = $this->timeContext->parse($user, $arguments['end_date'] ?? '')->startOfDay();
        $minimum = min(480, max(15, (int) ($arguments['minimum_minutes'] ?? 60)));
        $dayStart = $this->time($arguments['day_start'] ?? '08:00', 'day_start');
        $dayEnd = $this->time($arguments['day_end'] ?? '21:00', 'day_end');
        $slots = $this->availability->freeSlots($user, $start, $end, $dayStart, $dayEnd, $minimum);

        return [
            'range' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'minimum_minutes' => $minimum,
            'returned' => count($slots),
            'truncated' => count($slots) === 50,
            'slots' => $slots,
        ];
    }

    private function time(mixed $value, string $field): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw ValidationException::withMessages([$field => 'Gunakan format waktu HH:MM.']);
        }

        return $value;
    }
}
