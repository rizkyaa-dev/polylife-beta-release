<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Queries\User\AffiliationIndexQuery;
use Illuminate\Http\Request;

class AffiliationController extends Controller
{
    public function __construct(
        private readonly AffiliationIndexQuery $affiliationIndexQuery
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        return view('endmin.affiliations.index', [
            'affiliations' => $this->affiliationIndexQuery->build($search, $status),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
