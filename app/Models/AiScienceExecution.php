<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiScienceExecution extends Model
{
    protected $fillable = ['run_id', 'step_id', 'status', 'attempt', 'server_attempts', 'client_id', 'claim_token', 'lease_expires_at', 'deadline_at', 'private_payload'];

    protected $hidden = ['private_payload', 'client_id', 'claim_token'];

    protected $casts = ['attempt' => 'integer', 'server_attempts' => 'integer', 'private_payload' => 'encrypted:array',
        'lease_expires_at' => 'datetime', 'deadline_at' => 'datetime'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiChatRun::class, 'run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AiChatRunStep::class, 'step_id');
    }
}
