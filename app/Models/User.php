<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ADMIN_LEVEL_USER = 0;

    public const ADMIN_LEVEL_SUPER_ADMIN = 1;

    public const ADMIN_LEVEL_ADMIN = 2;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'account_status',
        'banned_at',
        'banned_by',
        'ban_reason_code',
        'ban_reason_text',
        'affiliation_type',
        'affiliation_name',
        'affiliation_template_id',
        'student_id_type',
        'student_id_number',
        'affiliation_status',
        'affiliation_verified_at',
        'affiliation_verified_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'integer',
        'banned_at' => 'datetime',
        'affiliation_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->is_admin = $user->adminLevel();
            $user->role = $user->roleKeyFromAdminLevel();
        });
    }

    public function adminLevel(): int
    {
        $level = (int) ($this->is_admin ?? self::ADMIN_LEVEL_USER);

        if (in_array($level, [self::ADMIN_LEVEL_USER, self::ADMIN_LEVEL_SUPER_ADMIN, self::ADMIN_LEVEL_ADMIN], true)) {
            return $level;
        }

        // Backward compatibility for older data where role was stored as string.
        $normalizedRole = strtolower(trim((string) ($this->role ?? '')));

        if ($normalizedRole === 'super_admin') {
            return self::ADMIN_LEVEL_SUPER_ADMIN;
        }

        if ($normalizedRole === 'admin') {
            return self::ADMIN_LEVEL_ADMIN;
        }

        return self::ADMIN_LEVEL_USER;
    }

    public function roleKeyFromAdminLevel(): string
    {
        return match ($this->adminLevel()) {
            self::ADMIN_LEVEL_SUPER_ADMIN => 'super_admin',
            self::ADMIN_LEVEL_ADMIN => 'admin',
            default => 'user',
        };
    }

    public function roleLabel(): string
    {
        return match ($this->adminLevel()) {
            self::ADMIN_LEVEL_SUPER_ADMIN => 'Super Admin',
            self::ADMIN_LEVEL_ADMIN => 'Admin',
            default => 'Pengguna',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->adminLevel() === self::ADMIN_LEVEL_SUPER_ADMIN;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminLevel() === self::ADMIN_LEVEL_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->adminLevel(), [self::ADMIN_LEVEL_SUPER_ADMIN, self::ADMIN_LEVEL_ADMIN], true);
    }

    public function canAccessSuperAdminPanel(): bool
    {
        return $this->isSuperAdmin() && $this->isActiveAccount();
    }

    public function isActiveAccount(): bool
    {
        return ($this->account_status ?? 'active') === 'active' && is_null($this->banned_at);
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isAdminOnly() && $this->isActiveAccount();
    }

    public function defaultDashboardRouteName(): string
    {
        if ($this->canAccessSuperAdminPanel()) {
            return 'endmin.dashboard';
        }

        if ($this->canAccessAdminPanel()) {
            return 'admin.dashboard';
        }

        return 'workspace.home';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

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

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function profileAvatar(): HasOne
    {
        return $this->hasOne(UserProfileAvatar::class);
    }

    public function affiliationTemplate()
    {
        return $this->belongsTo(AffiliationTemplate::class, 'affiliation_template_id');
    }

    public function adminAssignments(): HasMany
    {
        return $this->hasMany(AdminAssignment::class);
    }

    public function endminAuditLogsAsActor(): HasMany
    {
        return $this->hasMany(EndminAuditLog::class, 'actor_id');
    }

    public function endminAuditLogsAsTarget(): HasMany
    {
        return $this->hasMany(EndminAuditLog::class, 'target_user_id');
    }

    public function affiliationBroadcasts(): HasMany
    {
        return $this->hasMany(AffiliationBroadcast::class, 'created_by');
    }

    public function affiliationBroadcastPushLogs(): HasMany
    {
        return $this->hasMany(AffiliationBroadcastPushLog::class);
    }

    public function affiliationBroadcastReads(): HasMany
    {
        return $this->hasMany(AffiliationBroadcastRead::class);
    }

    public function affiliationRequests(): HasMany
    {
        return $this->hasMany(AffiliationRequest::class);
    }

    public function pendingAffiliationRequest(): HasOne
    {
        return $this->hasOne(AffiliationRequest::class)->where('status', AffiliationRequest::STATUS_PENDING)->latestOfMany();
    }

    public function offDays(): array
    {
        $days = $this->profile->preferences['off_days'] ?? null;

        if (is_array($days)) {
            return array_map('intval', $days);
        }

        return [0, 6]; // Default: Sunday, Saturday
    }

    public function isOffDay(\Illuminate\Support\Carbon $date): bool
    {
        // Routine off days
        if (in_array($date->dayOfWeek, $this->offDays(), true)) {
            return true;
        }

        // National holidays (if enabled in preferences, default is true)
        if ($this->profile->preferences['auto_national_holidays'] ?? true) {
            return app(\App\Services\HolidayService::class)->isHoliday($date);
        }

        return false;
    }

    public function getOffDayReason(\Illuminate\Support\Carbon $date): ?string
    {
        // National holidays take precedence for the label
        if ($this->profile->preferences['auto_national_holidays'] ?? true) {
            $nationalHoliday = app(\App\Services\HolidayService::class)->getHolidayName($date);
            if ($nationalHoliday) {
                return $nationalHoliday;
            }
        }

        if (in_array($date->dayOfWeek, $this->offDays(), true)) {
            return 'Hari libur rutin';
        }

        return null;
    }

    public function keuanganBudgets()
    {
        return $this->hasMany(KeuanganBudget::class);
    }
}
