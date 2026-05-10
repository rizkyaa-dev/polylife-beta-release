<?php

namespace App\Actions\Catatan;

use App\Models\Catatan;

class SaveCatatanAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(?Catatan $catatan, int $userId, array $validated): Catatan
    {
        $payload = [
            'user_id' => $userId,
            'judul' => trim((string) $validated['judul']),
            'isi' => (string) $validated['isi'],
            'preview_isi' => Catatan::makePreviewIsi((string) $validated['isi']),
            'show_preview' => (bool) ($validated['show_preview'] ?? false),
            'tanggal' => $validated['tanggal'],
        ];

        if ($catatan) {
            $catatan->update($payload);

            return $catatan->fresh();
        }

        $payload['status_sampah'] = false;

        return Catatan::query()->create($payload);
    }
}
