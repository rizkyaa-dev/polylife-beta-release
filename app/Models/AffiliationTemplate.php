<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliation_type',
        'affiliation_name',
        'aliases',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AffiliationRequest::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'affiliation_template_id');
    }

    public function adminAssignments(): HasMany
    {
        return $this->hasMany(AdminAssignment::class, 'affiliation_template_id');
    }

    public function broadcastTargets(): HasMany
    {
        return $this->hasMany(AffiliationBroadcastTarget::class, 'affiliation_template_id');
    }
}
