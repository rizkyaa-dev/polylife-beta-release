<?php

namespace App\Providers;

use App\Events\Security\UntrustedProxyHeadersDetected;
use App\Listeners\Security\LogUntrustedProxyHeaders;
use InvalidArgumentException;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(UntrustedProxyHeadersDetected::class, LogUntrustedProxyHeaders::class);

        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            $user = $request->user();

            if ($user) {
                return route($user->defaultDashboardRouteName());
            }

            return route('workspace.home');
        });

        if (! app()->runningInConsole()) {
            $requestHost = request()->getHost();
            $isLocalHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
            $hotFile = public_path('hot');

            if (is_file($hotFile)) {
                $hotUrl = trim((string) file_get_contents($hotFile));
                $hotHost = parse_url($hotUrl, PHP_URL_HOST);

                if (! $isLocalHost && $hotHost && $hotHost !== $requestHost) {
                    Vite::useHotFile(storage_path('app/vite.hot'));
                }
            } elseif (! app()->environment('local')) {
                Vite::useHotFile(storage_path('app/vite.hot'));
            }

            $appUrlScheme = strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME));
            if (! $isLocalHost && $appUrlScheme === 'https') {
                URL::forceScheme('https');
            }
        }

        Mail::extend('mailtrap', function (array $config = []) {
            $dsn = $config['dsn'] ?? null;

            if (! $dsn) {
                $token = $config['token'] ?? null;

                if (! $token) {
                    throw new InvalidArgumentException('MAILTRAP_DSN or MAILTRAP_API_TOKEN must be set for the mailtrap mailer.');
                }

                $dsn = 'mailtrap+api://'.rawurlencode($token).'@default';
            }

            return Transport::fromDsn($dsn);
        });

        View::composer('*', function ($view) {
            $guestMode = request()->routeIs('guest.*');
            $view->with('guestMode', $guestMode);
        });
    }
}
