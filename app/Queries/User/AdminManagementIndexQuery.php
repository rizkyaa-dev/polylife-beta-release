<?php

namespace App\Queries\User;

use App\Models\User;

class AdminManagementIndexQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $roleFilter, string $statusFilter, string $search): array
    {
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
                $subQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('affiliation_name', 'like', '%' . $search . '%');
            });
        }

        return [
            'users' => $query
                ->orderByRaw('CASE WHEN is_admin = 2 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->count(),
                'active_admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->where('account_status', 'active')->count(),
                'suspended_admins' => User::where('is_admin', User::ADMIN_LEVEL_ADMIN)->where('account_status', 'banned')->count(),
                'candidate_users' => User::where('is_admin', User::ADMIN_LEVEL_USER)->count(),
            ],
        ];
    }
}
