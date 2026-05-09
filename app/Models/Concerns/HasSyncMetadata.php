<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSyncMetadata
{
    protected static function bootHasSyncMetadata(): void
    {
        static::creating(function ($model): void {
            if (empty($model->sync_uuid)) {
                $model->sync_uuid = (string) Str::uuid();
            }

            if (empty($model->server_version)) {
                $model->server_version = 1;
            }
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('server_version')) {
                $model->server_version = max(1, (int) ($model->getOriginal('server_version') ?? 1)) + 1;
            }
        });

        static::deleting(function ($model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            if (! $model->exists || $model->isDirty('server_version')) {
                return;
            }

            $model->server_version = max(1, (int) ($model->getOriginal('server_version') ?? 1)) + 1;
            $model->saveQuietly();
        });
    }

    public function syncPayload(): array
    {
        return [
            'sync_uuid' => (string) $this->sync_uuid,
            'server_version' => (int) ($this->server_version ?? 1),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
