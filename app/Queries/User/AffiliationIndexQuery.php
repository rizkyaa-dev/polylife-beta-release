<?php

namespace App\Queries\User;

use App\Models\AffiliationTemplate;
use App\Models\User;

class AffiliationIndexQuery
{
    public function build(string $search, string $status)
    {
        $query = AffiliationTemplate::query()
            ->select([
                'id',
                'affiliation_type',
                'affiliation_name',
            ])
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->withCount([
                'users as total_users',
                'users as verified_users' => fn ($userQuery) => $userQuery->where('affiliation_status', 'verified'),
                'users as pending_users' => fn ($userQuery) => $userQuery->where('affiliation_status', 'pending'),
                'users as admin_count' => fn ($userQuery) => $userQuery->where('is_admin', User::ADMIN_LEVEL_ADMIN),
            ]);

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('affiliation_name', 'like', '%' . $search . '%')
                    ->orWhere('affiliation_type', 'like', '%' . $search . '%');
            });
        }

        if (in_array($status, ['verified', 'pending', 'rejected'], true)) {
            $query->whereHas('users', fn ($userQuery) => $userQuery->where('affiliation_status', $status));
        }

        return $query
            ->orderByDesc('total_users')
            ->orderBy('affiliation_name')
            ->paginate(20)
            ->withQueryString();
    }
}
