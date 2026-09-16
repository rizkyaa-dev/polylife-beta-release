<?php

namespace App\Services\Ai\Tools;

use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SearchTasksTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'search_tasks';
    }

    public function description(): string
    {
        return 'Mencari tugas akademik dan to-do, termasuk item yang sudah selesai, berdasarkan teks, jenis, status, atau deadline.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'query' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => ['all', 'tugas', 'todolist']],
                'status' => ['type' => 'string', 'enum' => ['all', 'pending', 'completed']],
                'deadline_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'deadline_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'limit' => ['type' => 'integer', 'description' => 'Maksimal 30 per jenis'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $kind = (string) ($arguments['kind'] ?? 'all');
        $status = (string) ($arguments['status'] ?? 'all');
        $text = trim((string) ($arguments['query'] ?? ''));
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 15)));
        $tasks = collect();
        $todos = collect();

        if ($kind !== 'todolist') {
            $query = Tugas::query()->where('user_id', $user->id);
            if ($text !== '') {
                $query->where(fn ($builder) => $builder->where('nama_tugas', 'like', '%'.$text.'%')->orWhere('deskripsi', 'like', '%'.$text.'%'));
            }
            if ($status !== 'all') {
                $query->where('status_selesai', $status === 'completed');
            }
            if (! empty($arguments['deadline_from'])) {
                $query->whereDate('deadline', '>=', $this->timeContext->parse($user, $arguments['deadline_from'])->toDateString());
            }
            if (! empty($arguments['deadline_to'])) {
                $query->whereDate('deadline', '<=', $this->timeContext->parse($user, $arguments['deadline_to'])->toDateString());
            }
            $tasks = $query->orderBy('deadline')->limit($limit)->get(['id', 'matkul_id', 'nama_tugas', 'deskripsi', 'deadline', 'status_selesai']);
        }

        if ($kind !== 'tugas') {
            $query = Todolist::query()->where('user_id', $user->id);
            if ($text !== '') {
                $query->where('nama_item', 'like', '%'.$text.'%');
            }
            if ($status !== 'all') {
                $query->where('status', $status === 'completed');
            }
            $todos = $query->latest('id')->limit($limit)->get(['id', 'nama_item', 'status']);
        }

        return [
            'tasks' => $tasks->map(fn (Tugas $task) => ['id' => $task->id, 'name' => $task->nama_tugas, 'description' => $task->deskripsi, 'deadline' => $task->deadline?->toIso8601String(), 'completed' => (bool) $task->status_selesai, 'course_id' => $task->matkul_id])->all(),
            'todos' => $todos->map(fn (Todolist $todo) => ['id' => $todo->id, 'name' => $todo->nama_item, 'completed' => (bool) $todo->status])->all(),
        ];
    }
}
