<?php

namespace App\Queries\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AffiliationIndexQuery
{
    public function build(string $search, string $status)
    {
        $query = User::query()
            ->select([
                'affiliation_type',
                'affiliation_name',
                DB::raw('COUNT(*) as total_users'),
                DB::raw("SUM(CASE WHEN affiliation_status = 'verified' THEN 1 ELSE 0 END) as verified_users"),
                DB::raw("SUM(CASE WHEN affiliation_status = 'pending' THEN 1 ELSE 0 END) as pending_users"),
                DB::raw("SUM(CASE WHEN is_admin = 2 THEN 1 ELSE 0 END) as admin_count"),
            ])
            ->whereNotNull('affiliation_name')
            ->where('affiliation_name', '!=', '')
            ->groupBy('affiliation_type', 'affiliation_name');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('affiliation_name', 'like', '%' . $search . '%')
                    ->orWhere('affiliation_type', 'like', '%' . $search . '%');
            });
        }

        if (in_array($status, ['verified', 'pending', 'rejected'], true)) {
            $query->where('affiliation_status', $status);
        }

        return $query
            ->orderByDesc('total_users')
            ->paginate(20)
            ->withQueryString();
    }
}
