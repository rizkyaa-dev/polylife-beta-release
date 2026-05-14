<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Affiliation\AffiliationNormalizer;

class AffiliationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliation_type',
        'affiliation_name',
        'normalized_name',
        'aliases',
        'is_active',
        'merged_into_id',
        'merged_by',
        'merged_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_active' => 'boolean',
            'merged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if (! $template->affiliation_name) {
                return;
            }

            $normalizer = app(AffiliationNormalizer::class);
            $template->affiliation_name = $normalizer->displayName((string) $template->affiliation_name);
            $template->normalized_name = $normalizer->nameKey((string) $template->affiliation_name);
            $template->affiliation_type = $normalizer->type($template->affiliation_type);
            $template->aliases = $normalizer->aliases((array) ($template->aliases ?? []));
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
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
