<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matkul_id_list',
        'jenis',
        'tanggal_mulai',
        'tanggal_selesai',
        'semester',
        'catatan_tambahan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
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
}
