<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ReminderTargetResolver;
use App\Services\Ai\UserTimeContext;

final class CreateReminderTool implements AiToolInterface
{
    public function __construct(
        private readonly UserTimeContext $timeContext,
        private readonly ReminderTargetResolver $targetResolver
    ) {}

    public function name(): string
    {
        return 'create_reminder';
    }

    public function description(): string
    {
        return 'Mengusulkan reminder untuk to-do, tugas, jadwal, atau kegiatan yang sudah ada. Jangan membuat item target baru.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'target_type' => ['type' => 'string', 'enum' => ['todolist', 'tugas', 'jadwal', 'kegiatan']],
                    'target_query' => ['type' => 'string', 'description' => 'Nama target yang sudah ada'],
                    'waktu_reminder' => ['type' => 'string', 'description' => 'Waktu reminder ISO 8601'],
                ],
                'required' => ['target_type', 'target_query', 'waktu_reminder'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $type = (string) ($arguments['target_type'] ?? '');
        $query = trim((string) ($arguments['target_query'] ?? ''));
        if ($query === '' || empty($arguments['waktu_reminder'])) {
            throw new AiActionException('Target dan waktu reminder wajib diisi.');
        }
        $target = $this->targetResolver->resolve($user, $type, $query);

        $whenValue = $this->timeContext->parse($user, $arguments['waktu_reminder'] ?? null);
        if (! $whenValue->isFuture()) {
            throw new AiActionException('Waktu reminder harus berada di masa depan.');
        }
        $when = $whenValue->format('Y-m-d H:i:s');
        $field = $type.'_id';

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Reminder {$query} pada {$when}",
            'payload' => [
                'reminder_target' => $type,
                $field => $target->getKey(),
                'waktu_reminder' => $when,
                'aktif' => true,
            ],
        ];
    }
}
