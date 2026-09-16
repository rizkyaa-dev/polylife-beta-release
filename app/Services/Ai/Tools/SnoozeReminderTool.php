<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ReminderRecordResolver;
use App\Services\Ai\UserTimeContext;

final class SnoozeReminderTool implements AiToolInterface
{
    public function __construct(
        private readonly ReminderRecordResolver $reminders,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'snooze_reminder';
    }

    public function description(): string
    {
        return 'Mengusulkan penundaan reminder aktif ke waktu baru. Perubahan wajib dikonfirmasi pengguna.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'target_type' => ['type' => 'string', 'enum' => ['todolist', 'tugas', 'jadwal', 'kegiatan']],
                'target_query' => ['type' => 'string'],
                'current_time' => ['type' => 'string', 'description' => 'Waktu lama bila target memiliki beberapa reminder'],
                'minutes' => ['type' => 'integer', 'description' => 'Tunda 5-10080 menit'],
                'until' => ['type' => 'string', 'description' => 'Alternatif waktu baru ISO 8601'],
            ], 'required' => ['target_type', 'target_query'],
        ]];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $type = (string) ($arguments['target_type'] ?? '');
        $query = (string) ($arguments['target_query'] ?? '');
        $reminder = $this->reminders->resolve($user, $type, $query, $arguments['current_time'] ?? null);
        if (isset($arguments['until'])) {
            $newTime = $this->timeContext->parse($user, $arguments['until']);
        } elseif (isset($arguments['minutes'])) {
            $minutes = min(10080, max(5, (int) $arguments['minutes']));
            $newTime = $this->timeContext->now($user)->addMinutes($minutes);
        } else {
            throw new AiActionException('Sebutkan durasi penundaan atau waktu reminder baru.');
        }

        $field = $type.'_id';

        return [
            'status' => 'proposal_created',
            'tool_name' => $this->name(),
            'summary' => 'Tunda reminder '.$query.' ke '.$newTime->format('d M Y H:i'),
            'payload' => [
                'reminder_id' => $reminder->id,
                'expected_updated_at' => $reminder->updated_at->format('Y-m-d H:i:s'),
                'reminder_target' => $type,
                $field => $reminder->getAttribute($field),
                'waktu_reminder' => $newTime->format('Y-m-d H:i:s'),
                'aktif' => true,
            ],
        ];
    }
}
