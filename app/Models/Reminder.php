<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'todolist_id',
        'tugas_id',
        'jadwal_id',
        'kegiatan_id',
        'waktu_reminder',
        'aktif',
    ];

    protected $casts = [
        'waktu_reminder' => 'datetime',
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
