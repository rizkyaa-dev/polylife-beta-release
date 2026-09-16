<?php

namespace App\Services\Ai\Tools;

use App\Models\Matkul;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;

final class CreateCourseTool implements AiToolInterface
{
    public function name(): string
    {
        return 'create_course';
    }

    public function description(): string
    {
        return 'Mengusulkan mata kuliah berulang lengkap dengan kode, dosen, semester, SKS, hari, jam, dan ruangan. Jangan menggantinya dengan jadwal tanggal tunggal.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'kode' => ['type' => 'string'], 'nama' => ['type' => 'string'],
                    'kelas' => ['type' => 'string'], 'dosen' => ['type' => 'string'],
                    'semester' => ['type' => 'integer'], 'sks' => ['type' => 'integer'],
                    'hari' => ['type' => 'string', 'description' => 'Nama hari Indonesia'],
                    'jam_mulai' => ['type' => 'string', 'description' => 'HH:mm'],
                    'jam_selesai' => ['type' => 'string', 'description' => 'HH:mm'],
                    'ruangan' => ['type' => 'string'], 'catatan' => ['type' => 'string'],
                ],
                'required' => ['kode', 'nama', 'kelas', 'dosen', 'semester', 'sks', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $code = strtoupper(trim((string) ($arguments['kode'] ?? '')));
        $name = trim((string) ($arguments['nama'] ?? ''));
        if ($code === '' || $name === '') {
            throw new AiActionException('Kode dan nama mata kuliah wajib diisi.');
        }
        if (Matkul::query()->ownedBy((int) $user->id)->whereRaw('LOWER(kode) = ?', [strtolower($code)])->exists()) {
            throw new AiActionException("Mata kuliah dengan kode {$code} sudah ada.");
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Mata Kuliah: {$code} - {$name}",
            'payload' => [
                'kode' => $code, 'nama' => $name,
                'kelas' => trim((string) ($arguments['kelas'] ?? '')),
                'dosen' => trim((string) ($arguments['dosen'] ?? '')),
                'semester' => (int) ($arguments['semester'] ?? 0),
                'sks' => (int) ($arguments['sks'] ?? 0),
                'hari' => ucfirst(strtolower(trim((string) ($arguments['hari'] ?? '')))),
                'jam_mulai' => trim((string) ($arguments['jam_mulai'] ?? '')),
                'jam_selesai' => trim((string) ($arguments['jam_selesai'] ?? '')),
                'ruangan' => trim((string) ($arguments['ruangan'] ?? '')),
                'warna_label' => '#2563eb',
                'catatan' => trim((string) ($arguments['catatan'] ?? '')),
            ],
        ];
    }
}
