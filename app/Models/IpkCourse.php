<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpkCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'ipk_id',
        'user_id',
        'matkul_id',
        'course_code',
        'course_name',
        'semester_reference',
        'sks',
        'grade_point',
        'grade_letter',
        'target_grade_point',
        'score_actual',
        'score_target',
        'is_retake',
        'status',
        'notes',
    ];

    protected $casts = [
        'grade_point' => 'float',
        'target_grade_point' => 'float',
        'score_actual' => 'float',
        'score_target' => 'float',
        'is_retake' => 'boolean',
    ];

    public function ipk()
    {
        return $this->belongsTo(Ipk::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matkul()
    {
        return $this->belongsTo(Matkul::class);
    }
}
