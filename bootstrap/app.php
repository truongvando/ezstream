<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // 🚀 SCHEDULING - Redis-first approach

        // ⚡ Bank check
        $schedule->command('bank:check-transactions')
                 ->everyMinute()
                 ->withoutOverlapping();

        // 📧 Subscription checks
        $schedule->command('subscriptions:check-expiring --days=3')
                 ->dailyAt('09:00')
                 ->withoutOverlapping();

        $schedule->command('subscriptions:check-expiring --days=1')
                 ->dailyAt('09:00')
                 ->withoutOverlapping();

        // 🧹 VPS cleanup
        $schedule->command('vps:cleanup')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();
        
        $schedule->command('vps:cleanup --force')
                 ->cron('0 */4 * * *')
                 ->withoutOverlapping();

        // ✅ Check scheduled streams
        $schedule->command('streams:check-scheduled')
                 ->everyMinute()
                 ->withoutOverlapping();

        // 🔧 Cleanup hanging streams
        $schedule->command('streams:force-stop-hanging --timeout=300')
                 ->everyFiveMinutes()
                 ->withoutOverlapping();

        // 🔄 Sync stream status with VPS reality (every 2 minutes - more responsive)
        $schedule->job(new \App\Jobs\SyncStreamStatusJob())
                 ->everyTwoMinutes()
                 ->withoutOverlapping();

        // 🩺 Redis health check
        $schedule->command('redis:health-check --connection=queue --fix')
                 ->everyTenMinutes()
                 ->withoutOverlapping();

        $schedule->command('vps:update-capacity')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
