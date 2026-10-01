<?php

namespace App\Models;

use App\Services\Ai\DTOs\LlmTokenUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatRun extends Model
{
    protected $fillable = [
        'request_id',
        'science_client',
        'science_execution_id',
        'claim_token',
        'request_fingerprint',
        'session_id',
        'branch_id',
        'user_message_id',
        'assistant_message_id',
        'previous_branch_id',
        'status',
        'attempts',
        'dispatch_attempts',
        'duration_ms',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'model_calls',
        'measured_model_calls',
        'error_code',
        'retryable',
        'started_at',
        'heartbeat_at',
        'last_dispatched_at',
        'lease_expires_at',
        'completed_at',
    ];

    protected $hidden = ['claim_token'];

    protected $casts = [
        'started_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'last_dispatched_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'retryable' => 'boolean',
        'science_client' => 'boolean',
        'science_execution_id' => 'integer',
        'attempts' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'model_calls' => 'integer',
        'measured_model_calls' => 'integer',
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

    public function tokenUsage(): LlmTokenUsage
    {
        return new LlmTokenUsage(
            promptTokens: (int) ($this->prompt_tokens ?? 0),
            completionTokens: (int) ($this->completion_tokens ?? 0),
            totalTokens: (int) ($this->total_tokens ?? 0)
        );
    }

    public function tokenUsageStatus(): string
    {
        if ($this->model_calls > 0 && $this->model_calls === $this->measured_model_calls) {
            return 'complete';
        }

        return $this->measured_model_calls > 0 || $this->total_tokens > 0 ? 'partial' : 'unknown';
    }
}
