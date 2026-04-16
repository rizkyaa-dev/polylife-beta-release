<?php

namespace App\Actions\Keuangan;

use App\Models\Keuangan;

class SaveKeuanganAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(?Keuangan $keuangan, int $userId, array $validated): Keuangan
    {
        $payload = [
            'user_id' => $userId,
            'jenis' => $validated['jenis'],
            'kategori' => trim((string) $validated['kategori']),
            'deskripsi' => ($deskripsi = trim((string) ($validated['deskripsi'] ?? ''))) !== '' ? $deskripsi : null,
            'nominal' => $validated['nominal'],
            'tanggal' => $validated['tanggal'],
        ];

        if ($keuangan) {
            $keuangan->update($payload);

            return $keuangan->fresh();
        }

        return Keuangan::query()->create($payload);
    }
}
