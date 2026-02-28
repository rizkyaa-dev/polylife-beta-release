<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Ensure the current user is a super admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessSuperAdminPanel()) {
            return $this->redirectForbiddenAccess($request);
        }

        return $next($request);
    }

    protected function redirectForbiddenAccess(Request $request): Response
    {
        $fallbackRoute = $request->user()?->defaultDashboardRouteName() ?? 'landing';
        $fallbackUrl = route($fallbackRoute);
        $currentUrl = rtrim($request->fullUrl(), '/');
        $previousUrl = rtrim((string) url()->previous(), '/');
        $appOrigin = rtrim(url('/'), '/');
        $isInternalPreviousUrl = $previousUrl !== ''
            && (
                $previousUrl === $appOrigin
                || str_starts_with($previousUrl, $appOrigin.'/')
                || str_starts_with($previousUrl, $appOrigin.'?')
            );

        if ($isInternalPreviousUrl && $previousUrl !== $currentUrl) {
            return redirect()->to($previousUrl)->with('error', 'Akses ditolak');
        }

        return redirect()->to($fallbackUrl)->with('error', 'Akses ditolak');
    }
}
