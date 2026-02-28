<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'affiliation_type',
        'affiliation_name',
        'position_name',
        'whatsapp_number',
        'instagram_handle',
        'telegram_username',
        'contact_email',
        'status',
        'assigned_by',
        'assigned_at',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
