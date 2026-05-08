<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebUserIsActive
{
    /**
     * Keep blocked accounts contained to the banned notice and logout routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActiveAccount()) {
            if ($request->routeIs('account.banned', 'logout')) {
                return $next($request);
            }

            return redirect()->route('account.banned');
        }

        return $next($request);
    }
}
