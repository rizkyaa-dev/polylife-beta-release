<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Ipk extends Model
{
    use HasFactory;

    protected static ?bool $hasTargetModeColumn = null;

    protected $fillable = [
        'user_id',
        'semester',
        'academic_year',
        'ips_actual',
        'ips_target',
        'ipk_running',
        'ipk_target',
        'status',
        'remarks',
        'target_mode',
    ];

    protected $casts = [
        'ips_actual' => 'float',
        'ips_target' => 'float',
        'ipk_running' => 'float',
        'ipk_target' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courses()
    {
        return $this->hasMany(IpkCourse::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        if (self::supportsTargetMode()) {
            $query->orderByRaw("CASE WHEN target_mode = 'ipk' THEN 0 ELSE 1 END");
        }

        return $query->orderBy('semester');
    }

    public static function supportsTargetMode(): bool
    {
        return self::hasTargetModeColumn();
    }

    protected static function hasTargetModeColumn(): bool
    {
        if (! is_null(self::$hasTargetModeColumn)) {
            return self::$hasTargetModeColumn;
        }

        try {
            return self::$hasTargetModeColumn = Schema::hasColumn('ipks', 'target_mode');
        } catch (\Throwable $e) {
            return self::$hasTargetModeColumn = false;
        }
    }
}
