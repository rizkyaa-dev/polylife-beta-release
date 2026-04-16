<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\User\ActivateAdminAction;
use App\Actions\User\DemoteAdminAction;
use App\Actions\User\PromoteAdminAction;
use App\Actions\User\SuspendAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Endmin\SuspendAdminRequest;
use App\Models\User;
use App\Queries\User\AdminManagementIndexQuery;
use Illuminate\Http\Request;

class AdminManagementController extends Controller
{
    public function __construct(
        private readonly AdminManagementIndexQuery $adminManagementIndexQuery,
        private readonly PromoteAdminAction $promoteAdminAction,
        private readonly SuspendAdminAction $suspendAdminAction,
        private readonly ActivateAdminAction $activateAdminAction,
        private readonly DemoteAdminAction $demoteAdminAction
    ) {
    }

    public function index(Request $request)
    {
        $roleFilter = (string) $request->query('role', '');
        $statusFilter = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $payload = $this->adminManagementIndexQuery->build($roleFilter, $statusFilter, $search);

        return view('endmin.admins.index', [
            'users' => $payload['users'],
            'filters' => [
                'role' => $roleFilter,
                'status' => $statusFilter,
                'q' => $search,
            ],
            'summary' => $payload['summary'],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function promote(Request $request, User $user)
    {
        ($this->promoteAdminAction)($request->user(), $user);

        return back()->with('success', 'Pengguna berhasil dijadikan admin.');
    }

    public function suspend(SuspendAdminRequest $request, User $user)
    {
        ($this->suspendAdminAction)($request->user(), $user, $request->validated()['reason'] ?? null);

        return back()->with('success', 'Admin berhasil disuspend.');
    }

    public function activate(Request $request, User $user)
    {
        ($this->activateAdminAction)($request->user(), $user);

        return back()->with('success', 'Admin berhasil diaktifkan kembali.');
    }

    public function demote(Request $request, User $user)
    {
        ($this->demoteAdminAction)($request->user(), $user);

        return back()->with('success', 'Status admin berhasil dicabut.');
    }
}
