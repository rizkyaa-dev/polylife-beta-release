<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use App\Services\Catatan\CatatanSearchIndexer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Catatan extends Model
{
    use HasFactory;
    use HasSyncMetadata;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sync_uuid',
        'server_version',
        'judul',
        'isi',
        'preview_isi',
        'show_preview',
        'tanggal',
        'status_sampah',
    ];

    protected static function booted(): void
    {
        static::saved(function (Catatan $catatan): void {
            app(CatatanSearchIndexer::class)->sync($catatan);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'show_preview' => 'boolean',
            'status_sampah' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeSelectSummary(Builder $query): Builder
    {
        return $query->select([
            'id',
            'user_id',
            'sync_uuid',
            'server_version',
            'judul',
            'preview_isi',
            'show_preview',
            'tanggal',
            'status_sampah',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    public function scopeSelectWebSummary(Builder $query): Builder
    {
        return $query->select([
            'id',
            'user_id',
            'sync_uuid',
            'server_version',
            'judul',
            'isi',
            'preview_isi',
            'show_preview',
            'tanggal',
            'status_sampah',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    public static function makePreviewIsi(?string $isi): string
    {
        return '';
    }

    public function previewForDisplay(): string
    {
        if (! $this->show_preview) {
            return '';
        }

        return Str::limit(
            Str::squish(strip_tags((string) $this->isi)),
            97,
            ''
        );
    }

    public function getIsiAttribute(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setIsiAttribute(mixed $value): void
    {
        $this->attributes['isi'] = Crypt::encryptString((string) $value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
