<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Endmin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $accountStatusFilter = trim((string) $request->query('account_status', ''));
        $emailStatusFilter = trim((string) $request->query('email_status', ''));

        $usersQuery = User::query()
            ->select([
                'id',
                'name',
                'email',
                'is_admin',
                'role',
                'account_status',
                'email_verified_at',
                'created_at',
            ]);

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('affiliation_name', 'like', '%'.$search.'%')
                    ->orWhere('student_id_number', 'like', '%'.$search.'%');
            });
        }

        if (in_array($roleFilter, ['super_admin', 'admin', 'user'], true)) {
            $level = match ($roleFilter) {
                'super_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
                'admin' => User::ADMIN_LEVEL_ADMIN,
                default => User::ADMIN_LEVEL_USER,
            };

            $usersQuery->where('is_admin', $level);
        }

        if (in_array($accountStatusFilter, ['active', 'banned'], true)) {
            $usersQuery->where('account_status', $accountStatusFilter);
        }

        if ($emailStatusFilter === 'verified') {
            $usersQuery->whereNotNull('email_verified_at');
        } elseif ($emailStatusFilter === 'unverified') {
            $usersQuery->whereNull('email_verified_at');
        }

        $users = $usersQuery
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = User::query()
            ->selectRaw('COUNT(*) as total_users')
            ->selectRaw('SUM(CASE WHEN is_admin = '.User::ADMIN_LEVEL_SUPER_ADMIN.' THEN 1 ELSE 0 END) as super_admins')
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) as unverified_users')
            ->first();

        return view('endmin.users.index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
                'account_status' => $accountStatusFilter,
                'email_status' => $emailStatusFilter,
            ],
            'stats' => [
                'total_users' => (int) ($stats->total_users ?? 0),
                'super_admins' => (int) ($stats->super_admins ?? 0),
                'unverified_users' => (int) ($stats->unverified_users ?? 0),
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function bulkProcess(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in([
                'verify_email',
                'activate_accounts',
                'ban_accounts',
                'delete_accounts',
                'promote_to_admin',
                'demote_to_user',
            ])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = $request->user();
        $actorId = (int) $actor->id;
        $action = (string) $validated['action'];
        $reason = trim((string) ($validated['reason'] ?? ''));
        $selectedIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedCount = $selectedIds->count();
        $eligibleIds = collect();
        $affectedCount = 0;
        $now = now();

        DB::transaction(function () use ($action, $actorId, $reason, $selectedIds, $now, &$eligibleIds, &$affectedCount): void {
            $baseQuery = User::query()->whereIn('id', $selectedIds);

            switch ($action) {
                case 'verify_email':
                    $eligibleIds = (clone $baseQuery)
                        ->whereNull('email_verified_at')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'email_verified_at' => $now,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'activate_accounts':
                    $eligibleIds = (clone $baseQuery)
                        ->where('account_status', '!=', 'active')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'account_status' => 'active',
                            'banned_at' => null,
                            'banned_by' => null,
                            'ban_reason_code' => null,
                            'ban_reason_text' => null,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'ban_accounts':
                    $banReason = $reason !== '' ? $reason : 'Bulk ban oleh super admin.';

                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', '!=', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->where('account_status', '!=', 'banned')
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'account_status' => 'banned',
                            'banned_at' => $now,
                            'banned_by' => $actorId,
                            'ban_reason_code' => 'bulk_ban',
                            'ban_reason_text' => $banReason,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'delete_accounts':
                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', '!=', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->delete();
                    break;

                case 'promote_to_admin':
                    $eligibleIds = (clone $baseQuery)
                        ->where('is_admin', User::ADMIN_LEVEL_USER)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'is_admin' => User::ADMIN_LEVEL_ADMIN,
                            'role' => 'admin',
                            'account_status' => 'active',
                            'banned_at' => null,
                            'banned_by' => null,
                            'ban_reason_code' => null,
                            'ban_reason_text' => null,
                            'updated_at' => $now,
                        ]);
                    break;

                case 'demote_to_user':
                    $eligibleIds = (clone $baseQuery)
                        ->where('id', '!=', $actorId)
                        ->where('is_admin', User::ADMIN_LEVEL_ADMIN)
                        ->pluck('id');

                    $affectedCount = User::query()
                        ->whereIn('id', $eligibleIds)
                        ->update([
                            'is_admin' => User::ADMIN_LEVEL_USER,
                            'role' => 'user',
                            'updated_at' => $now,
                        ]);
                    break;
            }
        });

        $processedIds = $eligibleIds->map(fn ($id) => (int) $id)->values();
        $skippedCount = max(0, $selectedCount - $affectedCount);

        AuditLogger::log(
            actor: $actor,
            module: 'users',
            action: 'bulk_'.$action,
            before: [
                'selected_count' => $selectedCount,
                'selected_user_ids' => $selectedIds->all(),
            ],
            after: [
                'affected_count' => $affectedCount,
                'processed_user_ids' => $processedIds->all(),
            ],
            context: [
                'skipped_count' => $skippedCount,
                'reason' => $reason !== '' ? $reason : null,
            ]
        );

        if ($affectedCount === 0) {
            return back()->withErrors(['bulk' => 'Tidak ada akun yang memenuhi syarat untuk aksi ini.']);
        }

        $message = "Bulk proses selesai. Berhasil: {$affectedCount}";
        if ($skippedCount > 0) {
            $message .= ", dilewati: {$skippedCount}";
        }
        $message .= '.';

        return back()->with('success', $message);
    }

    public function verificationIndex()
    {
        $roleFilter = trim((string) request()->query('role', ''));
        $affiliationStatusFilter = trim((string) request()->query('affiliation_status', ''));
        $emailStatusFilter = trim((string) request()->query('email_status', ''));
        $search = trim((string) request()->query('q', ''));

        $usersQuery = User::query();

        if (in_array($roleFilter, ['super_admin', 'admin', 'user'], true)) {
            $level = match ($roleFilter) {
                'super_admin' => User::ADMIN_LEVEL_SUPER_ADMIN,
                'admin' => User::ADMIN_LEVEL_ADMIN,
                default => User::ADMIN_LEVEL_USER,
            };

            $usersQuery->where('is_admin', $level);
        }

        if (in_array($affiliationStatusFilter, ['pending', 'verified', 'rejected'], true)) {
            $usersQuery->where('affiliation_status', $affiliationStatusFilter);
        }

        if ($emailStatusFilter === 'verified') {
            $usersQuery->whereNotNull('email_verified_at');
        } elseif ($emailStatusFilter === 'unverified') {
            $usersQuery->whereNull('email_verified_at');
        }

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('affiliation_name', 'like', '%'.$search.'%')
                    ->orWhere('student_id_number', 'like', '%'.$search.'%');
            });
        }

        $users = $usersQuery
            ->orderByRaw("CASE WHEN affiliation_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.verifications.index', [
            'users' => $users,
            'filters' => [
                'role' => $roleFilter,
                'affiliation_status' => $affiliationStatusFilter,
                'email_status' => $emailStatusFilter,
                'q' => $search,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function verificationEdit(User $user)
    {
        $superAdmins = User::query()
            ->where(function ($query) {
                $query->where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)
                    ->orWhere('role', 'super_admin');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('endmin.verifications.edit', [
            'user' => $user,
            'superAdmins' => $superAdmins,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function verificationUpdate(Request $request, User $user)
    {
        $validated = $request->validate([
            'email_verified' => ['nullable', 'boolean'],
            'affiliation_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
        ]);

        $before = $this->userSnapshot($user);
        $emailVerified = $request->boolean('email_verified');
        $user->email_verified_at = $emailVerified ? ($user->email_verified_at ?: now()) : null;

        $user->affiliation_status = $validated['affiliation_status'];
        if ($validated['affiliation_status'] === 'verified') {
            $user->affiliation_verified_at = $user->affiliation_verified_at ?: now();
            $user->affiliation_verified_by = Auth::id();
        } else {
            $user->affiliation_verified_at = null;
            $user->affiliation_verified_by = null;
        }

        $user->save();

        AuditLogger::log(
            actor: Auth::user(),
            module: 'verification',
            action: 'quick_update',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return back()
            ->with('success', 'Status verifikasi user berhasil diperbarui.');
    }

    public function verificationDetailUpdate(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'email_verified' => ['nullable', 'boolean'],
            'affiliation_type' => ['nullable', 'string', 'max:40'],
            'affiliation_name' => ['nullable', 'string', 'max:160'],
            'student_id_type' => ['nullable', 'string', 'max:32'],
            'student_id_number' => ['nullable', 'string', 'max:64'],
            'affiliation_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'affiliation_verified_at' => ['nullable', 'date'],
            'affiliation_verified_by' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->orWhere('role', 'super_admin');
                }),
            ],
        ]);

        $before = $this->userSnapshot($user);
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        $user->affiliation_type = $validated['affiliation_type'] ?: null;
        $user->affiliation_name = $validated['affiliation_name'] ?: null;
        $user->student_id_type = $validated['student_id_type'] ?: null;
        $user->student_id_number = $validated['student_id_number'] ?: null;
        $user->affiliation_status = $validated['affiliation_status'];

        $emailVerified = $request->boolean('email_verified');
        $user->email_verified_at = $emailVerified
            ? ($user->email_verified_at ?: now())
            : null;

        if ($validated['affiliation_status'] === 'verified') {
            $user->affiliation_verified_at = $validated['affiliation_verified_at']
                ? \Illuminate\Support\Carbon::parse($validated['affiliation_verified_at'])
                : ($user->affiliation_verified_at ?: now());
            $user->affiliation_verified_by = $validated['affiliation_verified_by'] ?: Auth::id();
        } else {
            $user->affiliation_verified_at = null;
            $user->affiliation_verified_by = null;
        }

        $user->save();

        AuditLogger::log(
            actor: Auth::user(),
            module: 'verification',
            action: 'detail_update',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return redirect()
            ->route('endmin.verifications.edit', $user)
            ->with('success', 'Detail verifikasi user berhasil diperbarui.');
    }

    public function edit(User $user)
    {
        return view('endmin.users.edit', [
            'user' => $user,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'account_status' => ['required', Rule::in(['active', 'banned'])],
            'ban_reason_code' => ['required_if:account_status,banned', 'nullable', 'string', 'max:50'],
            'ban_reason_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $this->userSnapshot($user);
        $isSelf = Auth::id() === $user->id;
        $targetIsSuperAdmin = $user->isSuperAdmin();

        if ($targetIsSuperAdmin && ! $isSelf && ! empty($validated['password'])) {
            return back()
                ->withErrors(['password' => 'Password super admin lain tidak dapat diubah.'])
                ->withInput();
        }

        if ($targetIsSuperAdmin && ! $isSelf && $validated['account_status'] === 'banned') {
            return back()
                ->withErrors(['account_status' => 'Super admin tidak dapat membanned sesama super admin.'])
                ->withInput();
        }

        if (
            $validated['account_status'] === 'banned'
            && Auth::id() === $user->id
        ) {
            return back()
                ->withErrors(['account_status' => 'Tidak bisa memblokir akun sendiri.'])
                ->withInput();
        }

        $emailChanged = $validated['email'] !== $user->email;
        $user->email = $validated['email'];
        $user->account_status = $validated['account_status'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($emailChanged) {
            $user->email_verified_at = $user->isSuperAdmin() ? now() : null;
        }

        if ($validated['account_status'] === 'banned') {
            $user->banned_at = $user->banned_at ?: now();
            $user->banned_by = Auth::id();
            $user->ban_reason_code = $validated['ban_reason_code'] ?? null;
            $user->ban_reason_text = $validated['ban_reason_text'] ?? null;
        } else {
            $user->banned_at = null;
            $user->banned_by = null;
            $user->ban_reason_code = null;
            $user->ban_reason_text = null;
        }

        $user->save();

        AuditLogger::log(
            actor: Auth::user(),
            module: 'users',
            action: 'update_account',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user),
            context: [
                'password_updated' => ! empty($validated['password']),
            ]
        );

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user() && $request->user()->id === $user->id) {
            abort(403, 'Tidak bisa menghapus akun sendiri.');
        }

        $before = $this->userSnapshot($user);
        $deletedUserId = $user->id;
        $user->delete();

        AuditLogger::log(
            actor: Auth::user(),
            module: 'users',
            action: 'delete_account',
            targetUser: null,
            before: $before,
            after: ['deleted' => true],
            context: ['deleted_user_id' => $deletedUserId]
        );

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function verify(User $user)
    {
        $before = $this->userSnapshot($user);

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        AuditLogger::log(
            actor: Auth::user(),
            module: 'users',
            action: 'verify_email',
            targetUser: $user,
            before: $before,
            after: $this->userSnapshot($user)
        );

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil diverifikasi.');
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
            'email_verified_at' => optional($user->email_verified_at)->toDateTimeString(),
            'affiliation_type' => $user->affiliation_type,
            'affiliation_name' => $user->affiliation_name,
            'affiliation_status' => $user->affiliation_status,
            'student_id_type' => $user->student_id_type,
            'student_id_number' => $user->student_id_number,
            'banned_at' => optional($user->banned_at)->toDateTimeString(),
            'banned_by' => $user->banned_by,
            'ban_reason_code' => $user->ban_reason_code,
            'ban_reason_text' => $user->ban_reason_text,
        ];
    }
}
