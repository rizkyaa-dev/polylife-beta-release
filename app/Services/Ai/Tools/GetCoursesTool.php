<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

final class GetCoursesTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_courses';
    }

    public function description(): string
    {
        return 'Mengambil daftar mata kuliah pengguna. Gunakan untuk pertanyaan tentang mata kuliah yang diambil, bukan agenda tanggal tertentu.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'semester' => ['type' => 'integer', 'description' => 'Filter nomor semester, opsional'],
                    'include_all' => ['type' => 'boolean', 'description' => 'True hanya bila pengguna meminta seluruh semester'],
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
        $baseQuery = Matkul::query()->ownedBy((int) $user->id);
        $semester = isset($arguments['semester'])
            ? (int) $arguments['semester']
            : (($arguments['include_all'] ?? false) ? null : (clone $baseQuery)->max('semester'));
        $courses = $baseQuery
            ->when($semester !== null, fn ($query) => $query->where('semester', $semester))
            ->orderByDesc('semester')->orderBy('nama')->limit(40)
            ->get(['id', 'kode', 'nama', 'kelas', 'dosen', 'semester', 'sks', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'])
            ->toArray();

        return ['semester' => $semester, 'count' => count($courses), 'courses' => $courses];
    }
}
