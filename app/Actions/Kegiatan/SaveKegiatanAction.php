<?php

namespace App\Actions\Kegiatan;

use App\Models\Jadwal;
use App\Models\Kegiatan;

class SaveKegiatanAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(?Kegiatan $kegiatan, int $userId, array $validated): Kegiatan
    {
        $jadwal = Jadwal::query()->findOrFail($validated['jadwal_id']);
        if ((int) $jadwal->user_id !== $userId) {
            abort(403, 'Akses ditolak');
        }

        $payload = [
            'jadwal_id' => $jadwal->id,
            'nama_kegiatan' => trim((string) $validated['nama_kegiatan']),
            'lokasi' => ($lokasi = trim((string) ($validated['lokasi'] ?? ''))) !== '' ? $lokasi : null,
            'tanggal_deadline' => $validated['tanggal_deadline'],
            'waktu' => $validated['waktu'],
            'status' => trim((string) $validated['status']),
        ];

        if ($kegiatan) {
            $kegiatan->update($payload);

            return $kegiatan->fresh();
        }

        return Kegiatan::query()->create($payload);
    }
}
