<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    protected $fillable = [
        'date',
        'name',
        'is_cuti_bersama',
        'year',
    ];

    protected $casts = [
        'date' => 'date',
        'is_cuti_bersama' => 'boolean',
        'year' => 'integer',
    ];

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }
}
