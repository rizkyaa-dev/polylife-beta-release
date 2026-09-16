<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class UpdateCourseTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_course';
    }

    public function description(): string
    {
        return 'Mengusulkan perubahan mata kuliah yang sudah ada: kode, nama, kelas, dosen, semester, SKS, jadwal, ruangan, warna, atau catatan.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Kode atau nama mata kuliah'],
                'kode' => ['type' => 'string'], 'nama' => ['type' => 'string'],
                'kelas' => ['type' => 'string'], 'dosen' => ['type' => 'string'],
                'semester' => ['type' => 'integer'], 'sks' => ['type' => 'integer'],
                'hari' => ['type' => 'string'], 'jam_mulai' => ['type' => 'string'],
                'jam_selesai' => ['type' => 'string'], 'ruangan' => ['type' => 'string'],
                'warna_label' => ['type' => 'string'], 'catatan' => ['type' => 'string'],
            ], 'required' => ['target']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Matkul $course */
        $course = $this->resolver->resolve($user, Matkul::class, ['kode', 'nama'], (string) ($arguments['target'] ?? ''), 'mata kuliah');
        if (collect($arguments)->except('target')->isEmpty()) {
            throw new AiActionException('Sebutkan bagian mata kuliah yang ingin diubah.');
        }

        $fields = ['kode', 'nama', 'kelas', 'dosen', 'semester', 'sks', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan', 'warna_label', 'catatan'];
        $payload = $course->only($fields);
        foreach ($fields as $field) {
            if (! array_key_exists($field, $arguments)) {
                continue;
            }
            $payload[$field] = in_array($field, ['semester', 'sks'], true)
                ? (int) $arguments[$field]
                : trim((string) $arguments[$field]);
        }
        $payload['kode'] = strtoupper((string) $payload['kode']);

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Perbarui Mata Kuliah: {$course->kode} - {$course->nama}",
            'payload' => ['matkul_id' => $course->id, 'expected_updated_at' => $course->updated_at->format('Y-m-d H:i:s')] + $payload,
        ];
    }
}
