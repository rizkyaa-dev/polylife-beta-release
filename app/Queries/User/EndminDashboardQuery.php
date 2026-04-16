<?php

namespace App\Queries\User;

use App\Models\EndminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EndminDashboardQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $roleDistribution = User::query()
            ->select('is_admin', DB::raw('COUNT(*) as total'))
            ->groupBy('is_admin')
            ->pluck('total', 'is_admin');

        return [
            'stats' => [
                'total_users' => User::count(),
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
        ];
    }
}
