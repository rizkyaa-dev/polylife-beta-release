<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

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
                $subQuery->where('affiliation_name', 'like', '%'.$search.'%')
                    ->orWhere('affiliation_type', 'like', '%'.$search.'%');
            });
        }

        if (in_array($status, ['verified', 'pending', 'rejected'], true)) {
            $query->where('affiliation_status', $status);
        }

        $affiliations = $query
            ->orderByDesc('total_users')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.affiliations.index', [
            'affiliations' => $affiliations,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
