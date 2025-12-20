<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kegiatan extends Model
{
    use HasFactory;

    protected $fillable = [
        'jadwal_id',
        'nama_kegiatan',
        'lokasi',
        'waktu',
        'tanggal_deadline',
        'status',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }
}
