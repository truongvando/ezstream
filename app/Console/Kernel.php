<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\VpsServer;
use App\Jobs\SyncVpsStatsJob;
use App\Jobs\SyncStreamStatusJob;
use App\Jobs\CheckBankTransactionsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 🚀 SCHEDULING - Redis-first approach

        // ⚡ Bank check
        $schedule->command('bank:check-transactions')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

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
                 ->withoutOverlapping()
                 ->runInBackground();
        
        $schedule->command('vps:cleanup --force')
                 ->cron('0 */4 * * *')
                 ->withoutOverlapping();

        // ✅ Check scheduled streams
        $schedule->command('streams:check-scheduled')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 🔧 Cleanup hanging streams
        $schedule->command('streams:force-stop-hanging --timeout=300')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 🔄 Sync stream status with VPS reality (every 2 minutes - more responsive)
        $schedule->job(new SyncStreamStatusJob())
                 ->everyTwoMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 🩺 Redis health check
        $schedule->command('redis:health-check --connection=queue --fix')
                 ->everyTenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('vps:update-capacity')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 