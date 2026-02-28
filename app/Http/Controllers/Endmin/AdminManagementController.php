<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\AdminAssignment;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminManagementController extends Controller
{
    public function index(Request $request)
    {
        $roleFilter = (string) $request->query('role', '');
        $statusFilter = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('is_admin', '!=', User::ADMIN_LEVEL_SUPER_ADMIN);

        if ($roleFilter === 'admin') {
            $query->where('is_admin', User::ADMIN_LEVEL_ADMIN);
        } elseif ($roleFilter === 'user') {
            $query->where('is_admin', User::ADMIN_LEVEL_USER);
        }

        if (in_array($statusFilter, ['active', 'banned'], true)) {
            $query->where('account_status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('affiliation_name', 'like', '%'.$search.'%');
            });
        }

        $users = $query
            ->orderByRaw('CASE WHEN is_admin = 2 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.admins.index', [
            'users' => $users,
            'filters' => [
                'role' => $roleFilter,
                'status' => $statusFilter,
                'q' => $search,
            ],
            'summary' => [
                'admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->count(),
                'active_admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->where('account_status', 'active')->count(),
                'suspended_admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->where('account_status', 'banned')->count(),
                'candidate_users' => User::where('is_admin', User::ADMIN_LEVEL_USER)->count(),
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function promote(User $user)
    {
        if ($user->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Akun super admin tidak dapat diubah melalui menu ini.']);
        }

        $before = $this->userSnapshot($user);

        $user->is_admin = User::ADMIN_LEVEL_ADMIN;
        $user->account_status = 'active';
        $user->banned_at = null;
        $user->banned_by = null;
        $user->ban_reason_code = null;
        $user->ban_reason_text = null;
        $user->save();

        $this->upsertAdminAssignment($user, 'active', null);

        AuditLogger::log(
            actor: Auth::user(),
            module: 'admin-management',
            action: 'promote_to_admin',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return back()->with('success', 'Pengguna berhasil dijadikan admin.');
    }

    public function suspend(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->isAdminOnly()) {
            return back()->withErrors(['role' => 'Hanya akun admin yang bisa disuspend.']);
        }

        $before = $this->userSnapshot($user);
        $reason = $validated['reason'] ?: 'Admin disuspend oleh super admin.';

        $user->account_status = 'banned';
        $user->banned_at = now();
        $user->banned_by = Auth::id();
        $user->ban_reason_code = 'admin_suspended';
        $user->ban_reason_text = $reason;
        $user->save();

        AdminAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ]);

        AuditLogger::log(
            actor: Auth::user(),
            module: 'admin-management',
            action: 'suspend_admin',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user),
            context: ['reason' => $reason]
        );

        return back()->with('success', 'Admin berhasil disuspend.');
    }

    public function activate(User $user)
    {
        if (! $user->isAdminOnly()) {
            return back()->withErrors(['role' => 'Hanya akun admin yang bisa diaktifkan dari menu ini.']);
        }

        $before = $this->userSnapshot($user);

        $user->account_status = 'active';
        $user->banned_at = null;
        $user->banned_by = null;
        $user->ban_reason_code = null;
        $user->ban_reason_text = null;
        $user->save();

        $this->upsertAdminAssignment($user, 'active', null);

        AuditLogger::log(
            actor: Auth::user(),
            module: 'admin-management',
            action: 'activate_admin',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return back()->with('success', 'Admin berhasil diaktifkan kembali.');
    }

    public function demote(User $user)
    {
        if (! $user->isAdminOnly()) {
            return back()->withErrors(['role' => 'Hanya akun admin yang bisa dicabut status admin-nya.']);
        }

        $before = $this->userSnapshot($user);

        $user->is_admin = User::ADMIN_LEVEL_USER;
        $user->save();

        AdminAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
                'revoke_reason' => 'Status admin dicabut oleh super admin.',
            ]);

        AuditLogger::log(
            actor: Auth::user(),
            module: 'admin-management',
            action: 'demote_admin',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return back()->with('success', 'Status admin berhasil dicabut.');
    }

    private function upsertAdminAssignment(User $user, string $status, ?string $revokeReason): void
    {
        $affiliationType = trim((string) ($user->affiliation_type ?? '')) ?: 'other';
        $affiliationName = trim((string) ($user->affiliation_name ?? '')) ?: 'Unassigned Affiliation';

        $payload = [
            'status' => $status,
            'contact_email' => $user->email,
        ];

        if ($status === 'active') {
            $payload = array_merge($payload, [
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
                'revoke_reason' => null,
            ]);
        } else {
            $payload = array_merge($payload, [
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
                'revoke_reason' => $revokeReason,
            ]);
        }

        AdminAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'affiliation_type' => $affiliationType,
                'affiliation_name' => $affiliationName,
            ],
            $payload
        );
    }

    private function userSnapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (int) $user->is_admin,
            'role' => $user->role,
            'account_status' => $user->account_status,
            'banned_at' => optional($user->banned_at)->toDateTimeString(),
            'ban_reason_code' => $user->ban_reason_code,
            'ban_reason_text' => $user->ban_reason_text,
            'affiliation_type' => $user->affiliation_type,
            'affiliation_name' => $user->affiliation_name,
        ];
    }
}
