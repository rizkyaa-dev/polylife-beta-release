<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class DuplicateScheduleTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'duplicate_schedule';
    }

    public function description(): string
    {
        return 'Mengusulkan duplikasi jadwal ke rentang tanggal baru. Kegiatan dapat ikut disalin dengan pergeseran tanggal, tetapi reminder tidak pernah disalin.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Judul jadwal sumber'],
                'tanggal_mulai' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'tanggal_selesai' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'title' => ['type' => 'string', 'description' => 'Judul salinan, opsional'],
                'copy_activities' => ['type' => 'boolean', 'description' => 'Default true'],
            ], 'required' => ['target', 'tanggal_mulai', 'tanggal_selesai']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Jadwal $source */
        $source = $this->resolver->resolve($user, Jadwal::class, ['title'], (string) ($arguments['target'] ?? ''), 'jadwal');
        if (empty($arguments['tanggal_mulai']) || empty($arguments['tanggal_selesai'])) {
            throw new AiActionException('Tanggal mulai dan selesai salinan wajib diisi.');
        }
        $start = $this->timeContext->parse($user, $arguments['tanggal_mulai'])->toDateString();
        $end = $this->timeContext->parse($user, $arguments['tanggal_selesai'])->toDateString();

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Duplikasi Jadwal: {$source->title} ke {$start}",
            'payload' => [
                'source_jadwal_id' => $source->id,
                'expected_updated_at' => $source->updated_at->format('Y-m-d H:i:s'),
                'title' => array_key_exists('title', $arguments) ? trim((string) $arguments['title']) ?: null : $source->title,
                'tanggal_mulai' => $start,
                'tanggal_selesai' => $end,
                'copy_activities' => array_key_exists('copy_activities', $arguments) ? (bool) $arguments['copy_activities'] : true,
            ],
        ];
    }
}
