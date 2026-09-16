<?php

namespace App\Services\Ai\Tools;

use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class ManageTugasTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'manage_tugas';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan tugas akademik yang sudah ada: tandai selesai, buka kembali, atau ganti nama.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'target' => ['type' => 'string', 'description' => 'Nama tugas yang sudah ada'],
                    'operation' => ['type' => 'string', 'enum' => ['complete', 'reopen', 'rename']],
                    'new_name' => ['type' => 'string', 'description' => 'Nama baru, wajib untuk rename'],
                ],
                'required' => ['target', 'operation'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Tugas $task */
        $task = $this->resolver->resolve($user, Tugas::class, ['nama_tugas'], (string) ($arguments['target'] ?? ''), 'tugas');
        $operation = (string) ($arguments['operation'] ?? '');
        $newName = trim((string) ($arguments['new_name'] ?? ''));

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => match ($operation) {
                'complete' => "Selesaikan Tugas: {$task->nama_tugas}",
                'reopen' => "Buka kembali Tugas: {$task->nama_tugas}",
                'rename' => "Ubah Tugas: {$task->nama_tugas} menjadi {$newName}",
                default => 'Perubahan tugas tidak valid',
            },
            'payload' => ['tugas_id' => $task->id, 'operation' => $operation, 'new_name' => $newName ?: null],
        ];
    }
}
