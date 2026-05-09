<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    use HasFactory;
    use HasSyncMetadata;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'sync_uuid',
        'server_version',
        'todolist_id',
        'tugas_id',
        'jadwal_id',
        'kegiatan_id',
        'waktu_reminder',
        'aktif',
    ];

    protected $casts = [
        'waktu_reminder' => 'datetime',
        'aktif' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function todolist()
    {
        return $this->belongsTo(Todolist::class);
    }

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class);
    }
}
