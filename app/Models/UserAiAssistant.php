<?php

namespace App\Models;

use App\Services\Ai\Enums\ThinkingEffort;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiAssistant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assistant_name',
        'personality_tone',
        'thinking_effort',
        'custom_instructions',
        'avatar_type',
    ];

    protected $casts = [
        'thinking_effort' => ThinkingEffort::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personaLabel(): string
    {
        return match ($this->personality_tone) {
            'casual' => 'Santai & Akrab',
            'formal' => 'Formal & Profesional',
            'strict_coach' => 'Mentor Disiplin',
            default => 'Sahabat Mahasiswa',
        };
    }
}
