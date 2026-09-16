<?php

namespace App\Services\Ai\Tools;

use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class MarkAnnouncementReadTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'mark_announcement_read';
    }

    public function description(): string
    {
        return 'Mengusulkan menandai satu pengumuman yang terlihat oleh pengguna sebagai sudah dibaca.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Judul pengumuman'],
            ], 'required' => ['target']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $query = AffiliationBroadcast::query()->visibleToUser($user);
        /** @var AffiliationBroadcast $announcement */
        $announcement = $this->resolver->resolveFromOwnedQuery($query, ['title'], (string) ($arguments['target'] ?? ''), 'pengumuman');

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Tandai pengumuman dibaca: {$announcement->title}",
            'payload' => ['announcement_id' => $announcement->id],
        ];
    }
}
