<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_id',
        'session_id',
        'user_id',
        'tool_name',
        'summary',
        'payload_json',
        'signature',
        'status',
        'expires_at',
        'executed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }
}
