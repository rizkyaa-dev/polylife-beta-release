<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationBroadcastPushLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'user_id',
        'endpoint',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(AffiliationBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
