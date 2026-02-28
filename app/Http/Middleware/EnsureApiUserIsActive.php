<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsActive
{
    /**
     * Block API access for non-active accounts and admin roles.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActiveAccount()) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            return response()->json([
                'message' => 'Akun ini sedang diblokir. Hubungi super admin.',
            ], 403);
        }

        if ($user && $user->isAdmin()) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            return response()->json([
                'message' => 'Akses API mobile hanya untuk akun pengguna biasa.',
            ], 403);
        }

        return $next($request);
    }
}
