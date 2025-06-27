<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\VpsServer;
use App\Jobs\SyncVpsStatsJob;
use App\Jobs\CheckBankTransactionsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('vps:sync-stats')->everyMinute();

        $schedule->call(function () {
            VpsServer::where('status', 'ACTIVE')->each(function ($vps) {
                SyncVpsStatsJob::dispatch($vps);
            });
        })->everyFiveMinutes();

        $schedule->job(new CheckBankTransactionsJob)->everyTwoMinutes();
        
        // Dọn dẹp VPS tự động lúc 2h sáng hàng ngày
        $schedule->command('vps:cleanup')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->emailOutputOnFailure('admin@example.com');
        
        // Kiểm tra disk usage khẩn cấp mỗi 4 tiếng
        $schedule->command('vps:cleanup --force')
                 ->cron('0 */4 * * *')
                 ->withoutOverlapping();
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