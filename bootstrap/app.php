<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureApiUserIsActive;
use App\Http\Middleware\EnsureWebUserIsActive;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\PreventDuplicateWrite;
use App\Http\Middleware\SanitizeForwardedHeaders;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\WorkspaceAccessMiddleware;
use App\Services\Ai\AiRunStateManager;
use App\Services\ReminderPushService;
use App\Support\Security\ProxyTrustSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'super-admin' => SuperAdminMiddleware::class,
            'workspace-access' => WorkspaceAccessMiddleware::class,
            'active-account' => EnsureWebUserIsActive::class,
            'prevent-back-history' => PreventBackHistory::class,
            'prevent-duplicate-write' => PreventDuplicateWrite::class,
            'api-active' => EnsureApiUserIsActive::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->web(append: [
            EnsureWebUserIsActive::class,
            SecurityHeaders::class,
        ]);
        $middleware->trustProxies(
            at: ProxyTrustSettings::trustedProxies(),
            headers: ProxyTrustSettings::trustedHeaders()
        );
        $middleware->prepend(SanitizeForwardedHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function () {
            app(ReminderPushService::class)->sendDueReminderPushes();
        })
            ->name('reminders.push.notifications')
            ->everyMinute();

        $schedule->call(function () {
            app(AiRunStateManager::class)->expireStale();
        })
            ->name('ai.runs.expire-stale')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->create();
