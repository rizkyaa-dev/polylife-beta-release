<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiMutu extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kampus',
        'program_studi',
        'kurikulum',
        'grades_plus_minus',
        'grades_ab',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'grades_plus_minus' => 'array',
        'grades_ab' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
