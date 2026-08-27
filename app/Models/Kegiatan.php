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

    public function getFormattedDatetimeAttribute(): string
    {
        if ($this->tanggal_deadline && $this->waktu) {
            return \Illuminate\Support\Carbon::parse($this->tanggal_deadline . ' ' . $this->waktu)->translatedFormat('d M H:i');
        }

        if ($this->tanggal_deadline) {
            return \Illuminate\Support\Carbon::parse($this->tanggal_deadline)->translatedFormat('d M');
        }

        if ($this->waktu) {
            return \Illuminate\Support\Carbon::parse($this->waktu)->translatedFormat('H:i');
        }

        return '';
    }

    public function getFullDatetimeAttribute(): ?\Illuminate\Support\Carbon
    {
        if ($this->tanggal_deadline && $this->waktu) {
            return \Illuminate\Support\Carbon::parse($this->tanggal_deadline . ' ' . $this->waktu);
        }

        if ($this->tanggal_deadline) {
            return \Illuminate\Support\Carbon::parse($this->tanggal_deadline);
        }

        if ($this->waktu) {
            return \Illuminate\Support\Carbon::parse($this->waktu);
        }

        return null;
    }
}
