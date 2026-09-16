<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class UpdateTugasTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'update_tugas';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan detail tugas akademik: nama, deskripsi, deadline, mata kuliah, atau status selesai.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Nama tugas yang sudah ada'],
                'nama_tugas' => ['type' => 'string'], 'deskripsi' => ['type' => 'string'],
                'deadline' => ['type' => 'string', 'description' => 'Waktu ISO 8601'],
                'mata_kuliah' => ['type' => 'string', 'description' => 'Kode atau nama mata kuliah'],
                'status_selesai' => ['type' => 'boolean'],
            ], 'required' => ['target']],
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
        if (collect($arguments)->except('target')->isEmpty()) {
            throw new AiActionException('Sebutkan bagian tugas yang ingin diubah.');
        }

        $courseId = $task->matkul_id;
        if (array_key_exists('mata_kuliah', $arguments)) {
            /** @var Matkul $course */
            $course = $this->resolver->resolve($user, Matkul::class, ['kode', 'nama'], (string) $arguments['mata_kuliah'], 'mata kuliah');
            $courseId = $course->id;
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Perbarui Tugas: {$task->nama_tugas}",
            'payload' => [
                'tugas_id' => $task->id,
                'expected_updated_at' => $task->updated_at->format('Y-m-d H:i:s'),
                'nama_tugas' => array_key_exists('nama_tugas', $arguments) ? trim((string) $arguments['nama_tugas']) : $task->nama_tugas,
                'deskripsi' => array_key_exists('deskripsi', $arguments) ? trim((string) $arguments['deskripsi']) ?: null : $task->deskripsi,
                'deadline' => array_key_exists('deadline', $arguments) ? $this->timeContext->parse($user, $arguments['deadline'])->format('Y-m-d H:i:s') : $task->deadline->format('Y-m-d H:i:s'),
                'status_selesai' => array_key_exists('status_selesai', $arguments) ? (bool) $arguments['status_selesai'] : (bool) $task->status_selesai,
                'matkul_id' => $courseId,
            ],
        ];
    }
}
