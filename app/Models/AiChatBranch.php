<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatBranch extends Model
{
    protected $fillable = [
        'session_id',
        'forked_from_message_id',
        'head_message_id',
        'context_digest',
        'digest_through_message_id',
    ];

    protected $casts = [
        'context_digest' => 'encrypted',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'branch_id');
    }
}
