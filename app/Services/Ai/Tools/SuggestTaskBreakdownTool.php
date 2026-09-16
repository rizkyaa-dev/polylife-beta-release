<?php

namespace App\Services\Ai\Tools;

use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class SuggestTaskBreakdownTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'suggest_task_breakdown';
    }

    public function description(): string
    {
        return 'Menyiapkan konteks tugas untuk dipecah menjadi langkah kecil. Hanya rekomendasi dan tidak membuat to-do.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Nama tugas yang ada di workspace'],
                'desired_steps' => ['type' => 'integer', 'description' => 'Jumlah langkah 3-10, default 5'],
                'available_minutes' => ['type' => 'integer', 'description' => 'Total waktu yang tersedia, opsional'],
            ], 'required' => ['target'],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Tugas $task */
        $task = $this->resolver->resolve($user, Tugas::class, ['nama_tugas'], (string) ($arguments['target'] ?? ''), 'tugas');

        return [
            'task' => [
                'id' => $task->id,
                'name' => $task->nama_tugas,
                'description' => $task->deskripsi,
                'deadline' => $task->deadline?->toIso8601String(),
                'course_id' => $task->matkul_id,
            ],
            'desired_steps' => min(10, max(3, (int) ($arguments['desired_steps'] ?? 5))),
            'available_minutes' => isset($arguments['available_minutes']) ? min(2880, max(15, (int) $arguments['available_minutes'])) : null,
            'instruction' => 'Susun langkah konkret, berurutan, dapat diverifikasi, dan realistis sebelum deadline. Jangan membuat to-do tanpa persetujuan pengguna.',
        ];
    }
}
