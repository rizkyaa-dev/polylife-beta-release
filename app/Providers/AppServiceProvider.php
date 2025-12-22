<?php

namespace App\Providers;

use InvalidArgumentException;
use Illuminate\Support\Facades\Mail;
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
        if (! app()->environment('local')) {
            Vite::useHotFile(storage_path('app/vite.hot'));
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
