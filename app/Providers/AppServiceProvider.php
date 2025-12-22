<?php

namespace App\Providers;

use InvalidArgumentException;
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

            $forwardedProto = request()->header('x-forwarded-proto');
            $usesHttps = request()->isSecure() || ($forwardedProto && str_contains($forwardedProto, 'https'));
            if (! $isLocalHost && $usesHttps) {
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
