<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'avatar_path',
        'bio',
        'phone',
        'date_of_birth',
        'gender',
        'location',
        'theme_preference',
        'timezone',
        'locale',
        'preferences',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatar(): HasOne
    {
        return $this->hasOne(UserProfileAvatar::class, 'user_id', 'user_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $avatarUpdatedAt = $this->relationLoaded('avatar')
            ? $this->avatar?->updated_at
            : $this->avatar()->value('updated_at');

        if ($avatarUpdatedAt) {
            return route('profile.avatar.show', [
                'user' => $this->user_id,
                'v' => strtotime((string) $avatarUpdatedAt) ?: null,
            ], false);
        }

        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }
}
