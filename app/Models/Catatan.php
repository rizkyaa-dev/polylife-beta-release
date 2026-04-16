<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Catatan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
        ];
    }

    public function scopeSelectSummary(Builder $query): Builder
    {
        return $query->select([
            'id',
            'user_id',
            'judul',
            'preview_isi',
            'tanggal',
            'status_sampah',
            'created_at',
            'updated_at',
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
