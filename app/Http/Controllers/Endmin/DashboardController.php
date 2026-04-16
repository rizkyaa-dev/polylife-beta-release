<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Queries\User\EndminDashboardQuery;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EndminDashboardQuery $endminDashboardQuery
    ) {
    }

    public function index()
    {
        $payload = $this->endminDashboardQuery->build();

        return view('endmin.dashboard.index', [
            'stats' => $payload['stats'],
            'roleDistribution' => $payload['roleDistribution'],
            'recentLogs' => $payload['recentLogs'],
            'pendingQueue' => $payload['pendingQueue'],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
