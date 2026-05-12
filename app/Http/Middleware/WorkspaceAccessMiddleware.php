<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActiveAccount()) {
            return redirect()->route('account.banned');
        }

        if ($user && $user->isAdmin()) {
            return redirect()->route($user->defaultDashboardRouteName());
        }

        return $next($request);
    }
}
