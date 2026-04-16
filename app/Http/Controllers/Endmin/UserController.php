<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\User\BulkProcessUsersAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateManagedUserAction;
use App\Actions\User\UpdateUserVerificationAction;
use App\Actions\User\UpdateUserVerificationDetailAction;
use App\Actions\User\VerifyUserEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Endmin\BulkProcessUsersRequest;
use App\Http\Requests\Endmin\UpdateManagedUserRequest;
use App\Http\Requests\Endmin\UpdateUserVerificationDetailRequest;
use App\Http\Requests\Endmin\UpdateUserVerificationRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly BulkProcessUsersAction $bulkProcessUsersAction,
        private readonly UpdateUserVerificationAction $updateUserVerificationAction,
        private readonly UpdateUserVerificationDetailAction $updateUserVerificationDetailAction,
        private readonly UpdateManagedUserAction $updateManagedUserAction,
        private readonly DeleteUserAction $deleteUserAction,
        private readonly VerifyUserEmailAction $verifyUserEmailAction
    ) {
    }

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
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('affiliation_name', 'like', '%' . $search . '%')
                    ->orWhere('student_id_number', 'like', '%' . $search . '%');
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
            ->selectRaw('SUM(CASE WHEN is_admin = ' . User::ADMIN_LEVEL_SUPER_ADMIN . ' THEN 1 ELSE 0 END) as super_admins')
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

    public function bulkProcess(BulkProcessUsersRequest $request)
    {
        $result = ($this->bulkProcessUsersAction)($request->user(), $request->validated());

        if ($result['affected_count'] === 0) {
            return back()->withErrors(['bulk' => 'Tidak ada akun yang memenuhi syarat untuk aksi ini.']);
        }

        $message = "Bulk proses selesai. Berhasil: {$result['affected_count']}";
        if ($result['skipped_count'] > 0) {
            $message .= ", dilewati: {$result['skipped_count']}";
        }
        $message .= '.';

        return back()->with('success', $message);
    }

    public function verificationIndex(Request $request)
    {
        $roleFilter = trim((string) $request->query('role', ''));
        $affiliationStatusFilter = trim((string) $request->query('affiliation_status', ''));
        $emailStatusFilter = trim((string) $request->query('email_status', ''));
        $search = trim((string) $request->query('q', ''));

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
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('affiliation_name', 'like', '%' . $search . '%')
                    ->orWhere('student_id_number', 'like', '%' . $search . '%');
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

    public function verificationUpdate(UpdateUserVerificationRequest $request, User $user)
    {
        ($this->updateUserVerificationAction)(
            $request->user(),
            $user,
            $request->validated(),
            $request->boolean('email_verified')
        );

        return back()->with('success', 'Status verifikasi user berhasil diperbarui.');
    }

    public function verificationDetailUpdate(UpdateUserVerificationDetailRequest $request, User $user)
    {
        ($this->updateUserVerificationDetailAction)(
            $request->user(),
            $user,
            $request->validated(),
            $request->boolean('email_verified')
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

    public function update(UpdateManagedUserRequest $request, User $user)
    {
        ($this->updateManagedUserAction)($request->user(), $user, $request->validated());

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        ($this->deleteUserAction)($request->user(), $user);

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function verify(Request $request, User $user)
    {
        ($this->verifyUserEmailAction)($request->user(), $user);

        return redirect()
            ->route('endmin.users.index')
            ->with('success', 'Akun berhasil diverifikasi.');
    }
}
