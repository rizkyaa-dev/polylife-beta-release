<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeuanganBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kategori',
        'nominal_limit',
        'bulan',
        'tahun',
    ];

    protected function casts(): array
    {
        return [
            'nominal_limit' => 'decimal:2',
            'bulan' => 'integer',
            'tahun' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
