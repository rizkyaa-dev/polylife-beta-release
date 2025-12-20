<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReminderPushLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'user_id',
        'milestone_seconds',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function reminder()
    {
        return $this->belongsTo(Reminder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
