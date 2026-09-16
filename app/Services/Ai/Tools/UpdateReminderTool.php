<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ReminderRecordResolver;
use App\Services\Ai\UserTimeContext;

final class UpdateReminderTool implements AiToolInterface
{
    public function __construct(
        private readonly ReminderRecordResolver $reminders,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'update_reminder';
    }

    public function description(): string
    {
        return 'Mengusulkan penjadwalan ulang, aktivasi, atau penonaktifan reminder yang sudah ada untuk target tertentu.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target_type' => ['type' => 'string', 'enum' => ['todolist', 'tugas', 'jadwal', 'kegiatan']],
                'target_query' => ['type' => 'string'],
                'current_time' => ['type' => 'string', 'description' => 'Waktu reminder lama untuk membedakan bila target memiliki beberapa reminder'],
                'new_time' => ['type' => 'string', 'description' => 'Waktu reminder baru ISO 8601'],
                'active' => ['type' => 'boolean'],
            ], 'required' => ['target_type', 'target_query']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        if (! array_key_exists('new_time', $arguments) && ! array_key_exists('active', $arguments)) {
            throw new AiActionException('Sebutkan waktu baru atau status aktif reminder.');
        }

        $type = (string) ($arguments['target_type'] ?? '');
        $targetQuery = (string) ($arguments['target_query'] ?? '');
        $target = $this->reminders->resolve($user, $type, $targetQuery, $arguments['current_time'] ?? null);
        $field = $type.'_id';
        $newTime = array_key_exists('new_time', $arguments)
            ? $this->timeContext->parse($user, $arguments['new_time'])->format('Y-m-d H:i:s')
            : $target->getRawOriginal('waktu_reminder');

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => 'Perbarui reminder '.$targetQuery,
            'payload' => [
                'reminder_id' => $target->id,
                'expected_updated_at' => $target->updated_at->format('Y-m-d H:i:s'),
                'reminder_target' => $type,
                $field => $target->getAttribute($field),
                'waktu_reminder' => $newTime,
                'aktif' => array_key_exists('active', $arguments) ? (bool) $arguments['active'] : (bool) $target->aktif,
            ],
        ];
    }
}
