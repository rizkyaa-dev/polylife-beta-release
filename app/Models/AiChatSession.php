<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'active_branch_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(AiActionProposal::class, 'session_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(AiChatBranch::class, 'session_id');
    }

    public function activeBranch(): BelongsTo
    {
        return $this->belongsTo(AiChatBranch::class, 'active_branch_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiChatRun::class, 'session_id');
    }
}
