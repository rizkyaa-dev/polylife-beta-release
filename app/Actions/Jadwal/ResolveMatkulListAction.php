<?php

namespace App\Actions\Jadwal;

use App\Models\Matkul;

class ResolveMatkulListAction
{
    public function __invoke(int $userId, array $selectedIds, bool $createMatkul, array $matkulData): ?string
    {
        $ids = collect($selectedIds);

        if ($createMatkul && ! empty($matkulData['matkul_nama'])) {
            $matkul = Matkul::query()->create([
                'user_id' => $userId,
                'kode' => $matkulData['matkul_kode'] ?? null,
                'nama' => $matkulData['matkul_nama'],
                'semester' => isset($matkulData['matkul_semester'])
                    ? (int) $matkulData['matkul_semester']
                    : null,
            ]);

            $ids->push($matkul->id);
        }

        $normalized = $ids
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return null;
        }

        return $normalized->implode(';') . ';';
    }
}
