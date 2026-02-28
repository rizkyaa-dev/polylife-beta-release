<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationBroadcastTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'affiliation_type',
        'affiliation_name',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(AffiliationBroadcast::class, 'broadcast_id');
    }
}
