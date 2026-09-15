<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatRun extends Model
{
    protected $fillable = [
        'session_id',
        'branch_id',
        'user_message_id',
        'assistant_message_id',
        'previous_branch_id',
        'status',
        'attempts',
        'duration_ms',
        'error_code',
        'retryable',
        'started_at',
        'heartbeat_at',
        'lease_expires_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'retryable' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(AiChatRunStep::class, 'run_id')->orderBy('sequence');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'assistant_message_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(AiChatBranch::class, 'branch_id');
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'user_message_id');
    }
}
