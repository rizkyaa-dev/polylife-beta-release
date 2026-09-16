<?php

namespace App\Services\Ai\Tools;

use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class RestoreNoteTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'restore_note';
    }

    public function description(): string
    {
        return 'Mengusulkan pemulihan catatan dari sampah ke daftar catatan aktif.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => ['target' => ['type' => 'string', 'description' => 'Judul catatan di sampah']], 'required' => ['target'],
        ]];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Catatan $note */
        $note = $this->resolver->resolve($user, Catatan::class, ['judul'], (string) ($arguments['target'] ?? ''), 'catatan', fn ($query) => $query->where('status_sampah', true));

        return ['status' => 'proposal_created', 'tool_name' => $this->name(), 'summary' => "Pulihkan Catatan: {$note->judul}", 'payload' => [
            'catatan_id' => $note->id, 'expected_updated_at' => $note->updated_at->format('Y-m-d H:i:s'),
        ]];
    }
}
