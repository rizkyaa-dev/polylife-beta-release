<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AffiliationBroadcast extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const TARGET_MODE_AFFILIATION = 'affiliation';
    public const TARGET_MODE_GLOBAL = 'global';

    protected $fillable = [
        'created_by',
        'title',
        'body',
        'image_path',
        'target_mode',
        'send_push',
        'status',
        'published_at',
        'push_started_at',
        'push_completed_at',
        'push_success_count',
        'push_failed_count',
    ];

    protected $casts = [
        'send_push' => 'boolean',
        'published_at' => 'datetime',
        'push_started_at' => 'datetime',
        'push_completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AffiliationBroadcastTarget::class, 'broadcast_id');
    }

    public function pushLogs(): HasMany
    {
        return $this->hasMany(AffiliationBroadcastPushLog::class, 'broadcast_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        return $query->published()
            ->where(function (Builder $visibilityQuery) use ($user) {
                $visibilityQuery->where('target_mode', self::TARGET_MODE_GLOBAL)
                    ->orWhere(function (Builder $affiliationQuery) use ($user) {
                        $affiliationQuery->where('target_mode', self::TARGET_MODE_AFFILIATION)
                            ->whereHas('targets', function (Builder $targetQuery) use ($user) {
                                $targetQuery->where('affiliation_name', (string) ($user->affiliation_name ?? ''))
                                    ->when(
                                        filled($user->affiliation_type),
                                        fn (Builder $typeQuery) => $typeQuery->where(function (Builder $matchTypeQuery) use ($user) {
                                            $matchTypeQuery->whereNull('affiliation_type')
                                                ->orWhere('affiliation_type', (string) $user->affiliation_type);
                                        }),
                                        fn (Builder $typeQuery) => $typeQuery->whereNull('affiliation_type')
                                    );
                            });
                    });
            });
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = trim((string) ($this->image_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            return '/'.$path;
        }

        return '/storage/'.$path;
    }
}
