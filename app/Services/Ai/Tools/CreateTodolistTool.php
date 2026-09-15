<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class CreateTodolistTool implements AiToolInterface
{
    public function name(): string
    {
        return 'create_todolist';
    }

    public function description(): string
    {
        return 'Mengusulkan penambahan item baru ke daftar To-Do list harian pengguna.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'item' => [
                        'type' => 'string',
                        'description' => 'Nama kegiatan atau hal yang harus dilakukan',
                    ],
                ],
                'required' => ['item'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $item = trim((string) ($arguments['item'] ?? ''));

        return [
            'status' => 'proposal_created',
            'tool_name' => $this->name(),
            'summary' => "To-Do: {$item}",
            'payload' => [
                'nama_item' => $item,
                'status' => false,
            ],
        ];
    }
}
