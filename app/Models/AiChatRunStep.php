<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatRunStep extends Model
{
    protected $fillable = [
        'run_id',
        'sequence',
        'kind',
        'status',
        'label',
        'tool_name',
        'duration_ms',
        'public_metadata',
        'private_payload',
    ];

    protected $casts = [
        'public_metadata' => 'array',
        'private_payload' => 'encrypted:array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiChatRun::class, 'run_id');
    }
}
