<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    public function keuangans()
    {
        return $this->hasMany(Keuangan::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }

    public function tugas()
    {
        return $this->hasMany(Tugas::class);
    }

    public function catatans()
    {
        return $this->hasMany(Catatan::class);
    }

    public function ipks()
    {
        return $this->hasMany(Ipk::class);
    }

    public function todolists()
    {
        return $this->hasMany(Todolist::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }
}
