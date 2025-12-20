<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tugas extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matkul_id',
        'nama_tugas',
        'deskripsi',
        'deadline',
        'status_selesai',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
