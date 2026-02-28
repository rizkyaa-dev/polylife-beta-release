<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceAccessMiddleware
{
    /**
     * Restrict super admin from accessing workspace routes directly.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActiveAccount()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Akun ini sedang diblokir. Hubungi super admin.')
                ->with('status', 'Akun ini sedang diblokir. Hubungi super admin.');
        }

        if ($user && $user->isSuperAdmin()) {
            return redirect()->route('endmin.dashboard');
        }

        return $next($request);
    }
}
