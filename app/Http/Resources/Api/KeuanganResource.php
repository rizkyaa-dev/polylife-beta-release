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
            'tanggal' => (string) $this->tanggal,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
