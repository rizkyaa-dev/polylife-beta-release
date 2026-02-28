<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\ReminderPushService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super-admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'workspace-access' => \App\Http\Middleware\WorkspaceAccessMiddleware::class,
            'prevent-back-history' => \App\Http\Middleware\PreventBackHistory::class,
            'api-active' => \App\Http\Middleware\EnsureApiUserIsActive::class,
        ]);
        $middleware->trustProxies(at: '*');
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
    })
    ->create();
