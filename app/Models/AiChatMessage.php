<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'parent_message_id',
        'branch_id',
        'revision_group_id',
        'role',
        'status',
        'content',
        'reasoning_content',
        'tool_calls_json',
        'tool_results_json',
        'error_code',
    ];

    protected $casts = [
        'tool_calls_json' => 'array',
        'tool_results_json' => 'array',
        'reasoning_content' => 'encrypted',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(AiChatBranch::class, 'branch_id');
    }

    public function run(): HasOne
    {
        return $this->hasOne(AiChatRun::class, 'assistant_message_id');
    }
}
