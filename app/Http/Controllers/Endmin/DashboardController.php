<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\EndminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $superAdmins = User::where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)->count();
        $admins = User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->count();
        $regularUsers = User::where('is_admin', User::ADMIN_LEVEL_USER)->count();
        $bannedUsers = User::where('account_status', 'banned')->count();
        $pendingAffiliations = User::where('affiliation_status', 'pending')->count();
        $unverifiedEmails = User::whereNull('email_verified_at')->count();

        $roleDistribution = User::query()
            ->select('is_admin', DB::raw('COUNT(*) as total'))
            ->groupBy('is_admin')
            ->pluck('total', 'is_admin');

        $recentLogs = EndminAuditLog::query()
            ->with(['actor:id,name,email', 'targetUser:id,name,email'])
            ->latest()
            ->limit(12)
            ->get();

        $pendingQueue = User::query()
            ->where(function ($query) {
                $query->where('affiliation_status', 'pending')
                    ->orWhereNull('email_verified_at');
            })
            ->orderByRaw("CASE WHEN affiliation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'affiliation_name', 'affiliation_status', 'email_verified_at', 'created_at']);

        return view('endmin.dashboard.index', [
            'stats' => [
                'total_users' => $totalUsers,
                'super_admins' => $superAdmins,
                'admins' => $admins,
                'regular_users' => $regularUsers,
                'banned_users' => $bannedUsers,
                'pending_affiliations' => $pendingAffiliations,
                'unverified_emails' => $unverifiedEmails,
            ],
            'roleDistribution' => [
                'super_admin' => (int) ($roleDistribution[User::ADMIN_LEVEL_SUPER_ADMIN] ?? 0),
                'admin' => (int) ($roleDistribution[User::ADMIN_LEVEL_ADMIN] ?? 0),
                'user' => (int) ($roleDistribution[User::ADMIN_LEVEL_USER] ?? 0),
            ],
            'recentLogs' => $recentLogs,
            'pendingQueue' => $pendingQueue,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
