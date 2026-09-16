<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

final class SearchCoursesTool implements AiToolInterface
{
    public function name(): string
    {
        return 'search_courses';
    }

    public function description(): string
    {
        return 'Mencari mata kuliah berdasarkan kode, nama, dosen, kelas, ruangan, atau semester dan mengembalikan ID canonical.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'query' => ['type' => 'string'],
                'semester' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'description' => 'Maksimal 40, default 20'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(40, max(1, (int) ($arguments['limit'] ?? 20)));
        $query = Matkul::query()->ownedBy((int) $user->id);
        $text = trim((string) ($arguments['query'] ?? ''));
        if ($text !== '') {
            $query->where(function ($builder) use ($text): void {
                $builder->where('kode', 'like', '%'.$text.'%')->orWhere('nama', 'like', '%'.$text.'%')
                    ->orWhere('dosen', 'like', '%'.$text.'%')->orWhere('kelas', 'like', '%'.$text.'%')
                    ->orWhere('ruangan', 'like', '%'.$text.'%');
            });
        }
        if (isset($arguments['semester'])) {
            $query->where('semester', (int) $arguments['semester']);
        }
        $total = (clone $query)->count();
        $courses = $query->orderByDesc('semester')->orderBy('kode')->limit($limit)->get();

        return [
            'total' => $total,
            'returned' => $courses->count(),
            'truncated' => $total > $courses->count(),
            'courses' => $courses->map(fn (Matkul $course): array => [
                'id' => $course->id, 'code' => $course->kode, 'name' => $course->nama,
                'semester' => $course->semester, 'credits' => $course->sks, 'lecturer' => $course->dosen,
                'schedule_entries' => $course->scheduleEntries()->all(),
            ])->all(),
        ];
    }
}
