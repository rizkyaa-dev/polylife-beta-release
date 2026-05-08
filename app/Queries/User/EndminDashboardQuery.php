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
        $userStats = User::query()
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw('SUM(CASE WHEN is_admin = ? THEN 1 ELSE 0 END) as super_admins', [User::ADMIN_LEVEL_SUPER_ADMIN])
            ->selectRaw('SUM(CASE WHEN is_admin = ? THEN 1 ELSE 0 END) as admins', [User::ADMIN_LEVEL_ADMIN])
            ->selectRaw('SUM(CASE WHEN is_admin = ? THEN 1 ELSE 0 END) as regular_users', [User::ADMIN_LEVEL_USER])
            ->selectRaw("SUM(CASE WHEN account_status = 'banned' THEN 1 ELSE 0 END) as banned_users")
            ->selectRaw("SUM(CASE WHEN affiliation_status = 'pending' THEN 1 ELSE 0 END) as pending_affiliations")
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) as unverified_emails')
            ->first();

        $activeSessions = fn () => DB::table('sessions')
            ->select('user_id', DB::raw('MAX(last_activity) as last_activity'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $activeSince)
            ->groupBy('user_id');

        return [
            'stats' => [
                'total_users' => (int) ($userStats->total_users ?? 0),
                'active_users' => DB::query()
                    ->fromSub($activeSessions(), 'active_sessions')
                    ->count(),
                'super_admins' => (int) ($userStats->super_admins ?? 0),
                'admins' => (int) ($userStats->admins ?? 0),
                'regular_users' => (int) ($userStats->regular_users ?? 0),
                'banned_users' => (int) ($userStats->banned_users ?? 0),
                'pending_affiliations' => (int) ($userStats->pending_affiliations ?? 0),
                'unverified_emails' => (int) ($userStats->unverified_emails ?? 0),
            ],
            'roleDistribution' => [
                'super_admin' => (int) ($userStats->super_admins ?? 0),
                'admin' => (int) ($userStats->admins ?? 0),
                'user' => (int) ($userStats->regular_users ?? 0),
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
