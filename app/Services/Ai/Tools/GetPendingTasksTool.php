<?php

namespace App\Services\Ai\Tools;

use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

class GetPendingTasksTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_pending_tasks';
    }

    public function description(): string
    {
        return 'Mengambil daftar tugas perkuliahan yang belum selesai dan to-do list aktif milik pengguna.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Jumlah maksimal tugas yang diambil (default 15)',
                    ],
                ],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 15)));

        $tugasQuery = Tugas::query()
            ->where('user_id', $user->id)
            ->where('status_selesai', false);
        $pendingTugasCount = (clone $tugasQuery)->count();
        $tugas = $tugasQuery
            ->orderBy('deadline')
            ->limit($limit)
            ->get(['id', 'nama_tugas', 'deadline', 'status_selesai', 'deskripsi']);

        $todosQuery = Todolist::query()
            ->where('user_id', $user->id)
            ->where('status', false);
        $activeTodosCount = (clone $todosQuery)->count();
        $todos = $todosQuery
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'nama_item', 'status']);

        return [
            'pending_tugas_count' => $pendingTugasCount,
            'pending_tugas_returned' => $tugas->count(),
            'pending_tugas_truncated' => $pendingTugasCount > $tugas->count(),
            'tugas' => $tugas->toArray(),
            'active_todos_count' => $activeTodosCount,
            'active_todos_returned' => $todos->count(),
            'active_todos_truncated' => $activeTodosCount > $todos->count(),
            'todos' => $todos->toArray(),
        ];
    }
}
