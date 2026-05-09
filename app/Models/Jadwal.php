<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jadwal extends Model
{
    use HasFactory;
    use HasSyncMetadata;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sync_uuid',
        'server_version',
        'matkul_id_list',
        'jenis',
        'tanggal_mulai',
        'tanggal_selesai',
        'semester',
        'catatan_tambahan',
        'title',
        'location',
        'start_time',
        'end_time',
        'is_completed',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_completed' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matkulIds()
    {
        if (!$this->matkul_id_list) {
            return collect();
        }
        return collect(explode(';', $this->matkul_id_list))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique();
    }

    public function kegiatans()
    {
        return $this->hasMany(Kegiatan::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
