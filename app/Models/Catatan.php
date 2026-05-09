<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'tanggal',
        'status_sampah',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
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
            'tanggal',
            'status_sampah',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    public static function makePreviewIsi(?string $isi): string
    {
        return Str::limit(
            Str::squish(strip_tags((string) $isi)),
            97,
            ''
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
