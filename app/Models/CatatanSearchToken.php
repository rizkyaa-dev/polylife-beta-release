<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatatanSearchToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'catatan_id',
        'user_id',
        'token_hash',
    ];
}
