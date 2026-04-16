<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatatanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();
        $hasFullIsi = array_key_exists('isi', $attributes);

        return [
            'id' => (int) $this->id,
            'judul' => (string) ($this->judul ?? ''),
            'preview_isi' => (string) ($this->preview_isi ?? ''),
            'has_full_isi' => $hasFullIsi,
            'isi' => $this->when($hasFullIsi, (string) ($this->isi ?? '')),
            'tanggal' => optional($this->tanggal)->toDateString() ?? (string) $this->tanggal,
            'status_sampah' => (bool) $this->status_sampah,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
