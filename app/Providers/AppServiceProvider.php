<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Schedule zombie cleanup and partition checks
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

                // Cleanup zombie processes every 10 minutes
                $schedule->job(new \App\Jobs\CleanupZombieProcessesJob)
                    ->everyTenMinutes()
                    ->name('cleanup-zombies')
                    ->withoutOverlapping();

                // Check for network partitions every 5 minutes
                $schedule->call(function () {
                    $vpsList = \App\Models\VpsServer::where('status', 'ACTIVE')->get();
                    foreach ($vpsList as $vps) {
                        \App\Services\NetworkPartitionHandler::handlePartition($vps->id);
                    }
                })->everyFiveMinutes()->name('check-partitions');

                // Monitor RTMP server health every 3 minutes
                $schedule->job(new \App\Jobs\MonitorRtmpHealthJob)
                    ->everyThreeMinutes()
                    ->name('monitor-rtmp-health')
                    ->withoutOverlapping();

                // Redis memory cleanup every hour
                $schedule->call(function () {
                    \App\Services\RedisMemoryManager::cleanup();
                })->hourly()->name('redis-cleanup');

                // Database connection monitoring every 15 minutes
                $schedule->call(function () {
                    $stats = \App\Services\DatabaseConnectionManager::monitorConnectionPool();
                    foreach ($stats as $connection => $stat) {
                        if ($stat['health'] === 'critical') {
                            \App\Services\DatabaseConnectionManager::emergencyCleanup();
                            break;
                        }
                    }
                })->everyFifteenMinutes()->name('db-connection-monitor');
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Use php.ini settings - don't override memory_limit
        ini_set('max_execution_time', '0'); // Unlimited execution time
        ini_set('max_input_vars', '50000');

        // Bind mock SSH service for local testing
        if (app()->environment('local') && config('app.use_mock_ssh', false)) {
            $this->app->bind(\App\Services\SshService::class, \App\Services\MockSshService::class);
        }
    }
}
