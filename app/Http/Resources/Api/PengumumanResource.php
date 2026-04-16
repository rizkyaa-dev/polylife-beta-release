<?php

namespace App\Http\Resources\Api;

use App\Models\AffiliationBroadcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PengumumanResource extends JsonResource
{
    public function __construct($resource, private readonly bool $withExcerpt = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $targets = $this->target_mode === AffiliationBroadcast::TARGET_MODE_GLOBAL
            ? []
            : $this->targets
                ->map(fn ($target) => [
                    'affiliation_type' => $target->affiliation_type,
                    'affiliation_name' => $target->affiliation_name,
                ])
                ->values()
                ->all();

        $payload = [
            'id' => (int) $this->id,
            'title' => (string) ($this->title ?? ''),
            'body' => (string) ($this->body ?? ''),
            'image_url' => $this->image_url,
            'status' => (string) ($this->status ?? ''),
            'target_mode' => (string) ($this->target_mode ?? ''),
            'published_at' => optional($this->published_at)->toIso8601String(),
            'creator' => [
                'id' => (int) ($this->creator?->id ?? 0),
                'name' => (string) ($this->creator?->name ?? 'Admin'),
            ],
            'targets' => $targets,
        ];

        if ($this->withExcerpt) {
            $payload['excerpt'] = Str::limit((string) ($this->body ?? ''), 220);
        }

        return $payload;
    }
}
