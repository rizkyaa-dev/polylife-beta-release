<?php

namespace App\Services\Ai\Tools;

use App\Models\Todolist;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class ManageTodolistTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'manage_todolist';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan to-do yang sudah ada: tandai selesai, buka kembali, atau ganti nama. Jangan gunakan create_todolist untuk mengubah item lama.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'target' => ['type' => 'string', 'description' => 'Nama to-do yang sudah ada'],
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
        /** @var Todolist $todo */
        $todo = $this->resolver->resolve($user, Todolist::class, ['nama_item'], (string) ($arguments['target'] ?? ''), 'to-do');
        $operation = (string) ($arguments['operation'] ?? '');
        $newName = trim((string) ($arguments['new_name'] ?? ''));

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => match ($operation) {
                'complete' => "Selesaikan To-Do: {$todo->nama_item}",
                'reopen' => "Buka kembali To-Do: {$todo->nama_item}",
                'rename' => "Ubah To-Do: {$todo->nama_item} menjadi {$newName}",
                default => 'Perubahan To-Do tidak valid',
            },
            'payload' => ['todolist_id' => $todo->id, 'operation' => $operation, 'new_name' => $newName ?: null],
        ];
    }
}
