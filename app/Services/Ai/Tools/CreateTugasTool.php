<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\UserTimeContext;

final class CreateTugasTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'create_tugas';
    }

    public function description(): string
    {
        return 'Mengusulkan tugas akademik dengan deadline. Gunakan untuk tugas kuliah/assignment, bukan to-do umum.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'nama_tugas' => ['type' => 'string', 'description' => 'Nama tugas akademik'],
                    'deskripsi' => ['type' => 'string', 'description' => 'Detail tugas, opsional'],
                    'deadline' => ['type' => 'string', 'description' => 'Deadline ISO 8601 dengan tanggal dan waktu'],
                    'mata_kuliah' => ['type' => 'string', 'description' => 'Kode atau nama mata kuliah, bila disebut pengguna'],
                ],
                'required' => ['nama_tugas', 'deadline'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $name = trim((string) ($arguments['nama_tugas'] ?? ''));
        if ($name === '' || empty($arguments['deadline'])) {
            throw new AiActionException('Nama tugas dan deadline wajib diisi.');
        }
        $deadline = $this->timeContext->parse($user, $arguments['deadline'] ?? null)->format('Y-m-d H:i:s');
        $course = $this->resolveCourse($user, trim((string) ($arguments['mata_kuliah'] ?? '')));

        $payload = [
            'nama_tugas' => $name,
            'deskripsi' => trim((string) ($arguments['deskripsi'] ?? '')) ?: null,
            'deadline' => $deadline,
            'status_selesai' => false,
        ];
        if ($course) {
            $payload['matkul_id'] = $course->id;
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Tugas: {$name} (deadline {$deadline})",
            'payload' => $payload,
        ];
    }

    private function resolveCourse(User $user, string $search): ?Matkul
    {
        if ($search === '') {
            return null;
        }

        $base = Matkul::query()->ownedBy((int) $user->id);
        $exact = (clone $base)->where(function ($query) use ($search): void {
            $query->whereRaw('LOWER(kode) = ?', [mb_strtolower($search)])
                ->orWhereRaw('LOWER(nama) = ?', [mb_strtolower($search)]);
        })->first();
        if ($exact) {
            return $exact;
        }

        $matches = $base->where(function ($query) use ($search): void {
            $query->where('kode', 'like', '%'.$search.'%')->orWhere('nama', 'like', '%'.$search.'%');
        })->limit(2)->get();
        if ($matches->count() > 1) {
            throw new AiActionException("Mata kuliah '{$search}' ambigu. Gunakan kode atau nama yang lebih spesifik.");
        }

        return $matches->first();
    }
}
