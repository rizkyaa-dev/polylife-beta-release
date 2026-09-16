<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class CopyCourseToSemesterTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'copy_course_to_semester';
    }

    public function description(): string
    {
        return 'Mengusulkan salinan mata kuliah ke semester lain. Karena kode mata kuliah unik per user, kode baru dibuat atau diberikan secara eksplisit.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'target' => ['type' => 'string', 'description' => 'Kode atau nama mata kuliah sumber'],
                'semester' => ['type' => 'integer'],
                'new_code' => ['type' => 'string', 'description' => 'Kode unik baru, opsional'],
            ], 'required' => ['target', 'semester']],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Matkul $source */
        $source = $this->resolver->resolve($user, Matkul::class, ['kode', 'nama'], (string) ($arguments['target'] ?? ''), 'mata kuliah');
        $semester = (int) ($arguments['semester'] ?? 0);
        if ($semester < 1 || $semester > 14) {
            throw new AiActionException('Semester tujuan harus berada antara 1 dan 14.');
        }
        $code = strtoupper(trim((string) ($arguments['new_code'] ?? '')));
        if ($code === '') {
            $code = strtoupper($source->kode).'-S'.$semester;
        }
        if (Matkul::query()->ownedBy((int) $user->id)->whereRaw('LOWER(kode) = ?', [mb_strtolower($code)])->exists()) {
            throw new AiActionException("Kode mata kuliah {$code} sudah digunakan. Berikan kode baru yang unik.");
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Salin {$source->kode} ke semester {$semester} sebagai {$code}",
            'payload' => [
                'source_matkul_id' => $source->id,
                'expected_updated_at' => $source->updated_at->format('Y-m-d H:i:s'),
                'kode' => $code,
                'semester' => $semester,
            ],
        ];
    }
}
