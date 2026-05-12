<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationBroadcastRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'broadcast_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(AffiliationBroadcast::class, 'broadcast_id');
    }
}
