<?php

namespace App\Services\Ai\Tools;

use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\ProposalFreshnessGuard;

final class ArchiveNoteTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'archive_note';
    }

    public function description(): string
    {
        return 'Mengusulkan memindahkan catatan aktif ke sampah yang masih dapat dipulihkan. Ini bukan penghapusan permanen.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => ['target' => ['type' => 'string', 'description' => 'Judul catatan aktif']], 'required' => ['target'],
        ]];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Catatan $note */
        $note = $this->resolver->resolve($user, Catatan::class, ['judul'], (string) ($arguments['target'] ?? ''), 'catatan', fn ($query) => $query->where('status_sampah', false));

        return ['status' => 'proposal_created', 'tool_name' => $this->name(), 'summary' => "Arsipkan Catatan: {$note->judul}", 'payload' => [
            'catatan_id' => $note->id, 'expected_record_hash' => ProposalFreshnessGuard::snapshot($note),
        ]];
    }
}
