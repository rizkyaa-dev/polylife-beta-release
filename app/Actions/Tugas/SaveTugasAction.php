<?php

namespace App\Actions\Tugas;

use App\Models\Tugas;

class SaveTugasAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(?Tugas $tugas, int $userId, array $validated, bool $statusSelesai): Tugas
    {
        $payload = [
            'user_id' => $userId,
            'nama_tugas' => trim((string) $validated['nama_tugas']),
            'deskripsi' => ($deskripsi = trim((string) ($validated['deskripsi'] ?? ''))) !== '' ? $deskripsi : null,
            'deadline' => $validated['deadline'],
            'status_selesai' => $statusSelesai,
        ];
        if (array_key_exists('matkul_id', $validated)) {
            $payload['matkul_id'] = $validated['matkul_id'];
        }

        if ($tugas) {
            $tugas->update($payload);

            return $tugas->fresh();
        }

        return Tugas::query()->create($payload);
    }
}
