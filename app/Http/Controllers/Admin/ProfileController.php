<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Broadcast\BroadcastTargetOptionsQuery;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly BroadcastTargetOptionsQuery $broadcastTargetOptionsQuery
    ) {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user()->load([
            'profile',
            'pendingAffiliationRequest',
            'adminAssignments' => fn ($query) => $query
                ->where('status', 'active')
                ->orderBy('affiliation_name'),
        ]);

        $targetContext = $this->broadcastTargetOptionsQuery->forActor($user);

        return view('admin.profile', [
            'user' => $user,
            'targetOptions' => $targetContext['targetOptions'],
            'creationBlocked' => $targetContext['creationBlocked'],
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }
}
