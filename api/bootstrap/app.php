<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'auth/*',
            'v1/*',
            'billing/*',
            'webhook/*',
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'banned' => \App\Http\Middleware\CheckBanned::class,
            'api_key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'api_log' => \App\Http\Middleware\ApiLogger::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('blog:generate-ai')->dailyAt('09:00');
        $schedule->command('proxies:cleanup')->hourly();
        $schedule->command('proxies:refresh-stock')->everyThirtyMinutes();
        $schedule->command('isp:deprovision-expired')->hourly();
        $schedule->command('referral:process-pending')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
