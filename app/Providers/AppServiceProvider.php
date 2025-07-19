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
        //
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
