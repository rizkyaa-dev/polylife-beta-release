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
        $summary = User::query()
            ->selectRaw('SUM(CASE WHEN is_admin = ? THEN 1 ELSE 0 END) as admins', [User::ADMIN_LEVEL_ADMIN])
            ->selectRaw("SUM(CASE WHEN is_admin = ? AND account_status = 'active' THEN 1 ELSE 0 END) as active_admins", [User::ADMIN_LEVEL_ADMIN])
            ->selectRaw("SUM(CASE WHEN is_admin = ? AND account_status = 'banned' THEN 1 ELSE 0 END) as suspended_admins", [User::ADMIN_LEVEL_ADMIN])
            ->selectRaw('SUM(CASE WHEN is_admin = ? THEN 1 ELSE 0 END) as candidate_users', [User::ADMIN_LEVEL_USER])
            ->first();

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

        return [
            'users' => $query
                ->orderByRaw('CASE WHEN is_admin = 2 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'admins' => (int) ($summary->admins ?? 0),
                'active_admins' => (int) ($summary->active_admins ?? 0),
                'suspended_admins' => (int) ($summary->suspended_admins ?? 0),
                'candidate_users' => (int) ($summary->candidate_users ?? 0),
            ],
        ];
    }
}
