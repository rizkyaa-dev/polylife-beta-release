<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeuanganResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'jenis' => (string) $this->jenis,
            'kategori' => (string) ($this->kategori ?? ''),
            'deskripsi' => $this->deskripsi,
            'nominal' => (float) $this->nominal,
            'tanggal' => optional($this->tanggal)->toDateString() ?? (string) $this->tanggal,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'sync_uuid' => (string) ($this->sync_uuid ?? ''),
            'server_version' => (int) ($this->server_version ?? 1),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
