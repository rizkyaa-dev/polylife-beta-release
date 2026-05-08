<?php

namespace App\Queries\User;

use App\Models\EndminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EndminDashboardQuery
{
    private const ACTIVE_WINDOW_MINUTES = 15;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $activeSince = now()->subMinutes(self::ACTIVE_WINDOW_MINUTES)->timestamp;
        $roleDistribution = User::query()
            ->select('is_admin', DB::raw('COUNT(*) as total'))
            ->groupBy('is_admin')
            ->pluck('total', 'is_admin');

        $activeSessions = fn () => DB::table('sessions')
            ->select('user_id', DB::raw('MAX(last_activity) as last_activity'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $activeSince)
            ->groupBy('user_id');

        return [
            'stats' => [
                'total_users' => User::count(),
                'active_users' => DB::query()
                    ->fromSub($activeSessions(), 'active_sessions')
                    ->count(),
                'super_admins' => User::where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)->count(),
                'admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->count(),
                'regular_users' => User::where('is_admin', User::ADMIN_LEVEL_USER)->count(),
                'banned_users' => User::where('account_status', 'banned')->count(),
                'pending_affiliations' => User::where('affiliation_status', 'pending')->count(),
                'unverified_emails' => User::whereNull('email_verified_at')->count(),
            ],
            'roleDistribution' => [
                'super_admin' => (int) ($roleDistribution[User::ADMIN_LEVEL_SUPER_ADMIN] ?? 0),
                'admin' => (int) ($roleDistribution[User::ADMIN_LEVEL_ADMIN] ?? 0),
                'user' => (int) ($roleDistribution[User::ADMIN_LEVEL_USER] ?? 0),
            ],
            'recentLogs' => EndminAuditLog::query()
                ->with(['actor:id,name,email', 'targetUser:id,name,email'])
                ->latest()
                ->limit(12)
                ->get(),
            'pendingQueue' => User::query()
                ->where(function ($query) {
                    $query->where('affiliation_status', 'pending')
                        ->orWhereNull('email_verified_at');
                })
                ->orderByRaw("CASE WHEN affiliation_status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'name', 'email', 'affiliation_name', 'affiliation_status', 'email_verified_at', 'created_at']),
            'activeUsers' => User::query()
                ->joinSub($activeSessions(), 'active_sessions', function ($join) {
                    $join->on('users.id', '=', 'active_sessions.user_id');
                })
                ->orderByDesc('active_sessions.last_activity')
                ->limit(8)
                ->get([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.is_admin',
                    'users.role',
                    'users.account_status',
                    'users.affiliation_name',
                    DB::raw('active_sessions.last_activity as last_activity'),
                ]),
            'activeWindowMinutes' => self::ACTIVE_WINDOW_MINUTES,
        ];
    }
}
