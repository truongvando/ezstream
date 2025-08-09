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

        // 📺 YouTube channels sync
        $schedule->command('youtube:sync')
                 ->dailyAt('06:00')
                 ->withoutOverlapping();

        // 🔍 Check YouTube video status (detect dead/removed videos)
        $schedule->command('youtube:check-video-status --limit=200')
                 ->everyFourHours()
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

        // 🔧 Monitor stream health and auto-fix issues
        $schedule->command('streams:monitor-health --auto-fix')
                 ->everyTenMinutes()
                 ->withoutOverlapping();

        // 🔄 Sync stream state with all VPS agents (the new Master-Slave sync)
        $schedule->command('stream:sync')
                 ->everyTwoMinutes()
                 ->withoutOverlapping();

        // 👻 Cleanup ghost streams - đã tích hợp vào stream:sync
        // $schedule->command('streams:cleanup-ghosts')->everyFiveMinutes()->withoutOverlapping();



        // ⏰ Handle STOPPING timeout (every minute - critical for stuck streams)
        $schedule->job(new \App\Jobs\HandleStoppingTimeoutJob())
                 ->everyMinute()
                 ->withoutOverlapping();

        // 🩺 Redis health check
        $schedule->command('redis:health-check --connection=queue --fix')
                 ->everyTenMinutes()
                 ->withoutOverlapping();

        $schedule->command('vps:update-capacity')->everyFiveMinutes();

        // 🛑 Stop streams of expired users
        $schedule->command('streams:stop-expired-users')
                 ->hourly()
                 ->withoutOverlapping();

        // 🚦 Process stream queue (when VPS overloaded)
        $schedule->job(new \App\Jobs\ProcessStreamQueueJob())
                 ->everyThirtySeconds()
                 ->withoutOverlapping();

        // 🗑️ Process scheduled file deletions (Quick Stream auto-delete)
        $schedule->job(new \App\Jobs\ProcessScheduledDeletionsJob())
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // 🗑️ Manual auto-delete command (can be triggered manually)
        $schedule->command('videos:auto-delete --process-scheduled')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping();

        // 📋 Process scheduled orders (hẹn giờ đặt hàng)
        $schedule->job(new \App\Jobs\ProcessScheduledOrdersJob())
                 ->everyMinute()
                 ->withoutOverlapping();

        // 🔍 Check view order status (sync với JAP)
        $schedule->command('orders:check-status')
                 ->everyFiveMinutes()
                 ->withoutOverlapping();

        // 🔄 Sync JAP services (cập nhật danh sách services từ nhà cung cấp)
        $schedule->command('jap:sync-services')
                 ->dailyAt('03:00')
                 ->withoutOverlapping();

        // 🔍 Kiểm tra services khả dụng (check service availability)
        $schedule->command('jap:check-service-availability')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // 💱 Cập nhật tỷ giá USD/VND (update exchange rate) - ĐÃ CÓ SẴN TRONG AppServiceProvider
        // $schedule->command('exchange-rate update')->hourly()->withoutOverlapping();

        // 🔍 Kiểm tra currency consistency
        $schedule->command('currency:check-consistency')
                 ->dailyAt('04:00')
                 ->withoutOverlapping();

        // 💰 Process pending deposits (check bank transactions)
        $schedule->command('deposits:process-pending')
                 ->everyMinute()
                 ->withoutOverlapping();

        // 💾 Daily database backup at 2 AM
        $schedule->command('backup:database --compress')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // 🎬 Process video processing queue every minute
        $schedule->command('video:process-queue --once')
                 ->everyMinute()
                 ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            // Use the built-in TrustProxies middleware from the framework
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->group('api', [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'agent.token' => \App\Http\Middleware\VerifyAgentToken::class,
            // 'auth' => \App\Http\Middleware\Authenticate::class,
            // 'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            // 'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // 'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            // 'can' => \Illuminate\Auth\Middleware\Authorize::class,
            // 'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            // 'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            // 'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            // 'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e) {
            if (\App\Exceptions\RedisConnectionHandler::isConnectionError($e)) {
                \App\Exceptions\RedisConnectionHandler::handleConnectionError($e, 'Queue Worker');
                return false; // Don't report to default logger
            }
        });
    })->create();
