<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'operation_uuid',
        'entity_type',
        'entity_id',
        'action',
        'status',
        'response_json',
        'error_json',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'error_json' => 'array',
        ];
    }
}
