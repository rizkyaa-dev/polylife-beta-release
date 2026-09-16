<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\ScheduleAvailabilityService;
use App\Services\Ai\UserTimeContext;

final class CheckScheduleConflictsTool implements AiToolInterface
{
    public function __construct(
        private readonly ScheduleAvailabilityService $availability,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'check_schedule_conflicts';
    }

    public function description(): string
    {
        return 'Memeriksa bentrok suatu rentang waktu dengan jadwal pengguna tanpa mengubah data.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'start_at' => ['type' => 'string', 'description' => 'Tanggal dan waktu mulai ISO 8601'],
                'end_at' => ['type' => 'string', 'description' => 'Tanggal dan waktu selesai ISO 8601'],
                'exclude_schedule_id' => ['type' => 'integer', 'description' => 'ID jadwal yang sedang diedit, opsional'],
            ], 'required' => ['start_at', 'end_at'],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $start = $this->timeContext->parse($user, $arguments['start_at'] ?? '');
        $end = $this->timeContext->parse($user, $arguments['end_at'] ?? '');
        $conflicts = $this->availability->conflicts(
            $user,
            $start,
            $end,
            isset($arguments['exclude_schedule_id']) ? (int) $arguments['exclude_schedule_id'] : null
        );

        return [
            'requested_interval' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'has_conflict' => $conflicts !== [],
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];
    }
}
