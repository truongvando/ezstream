<?php

namespace App\Console\Commands;

use App\Jobs\HandleStoppingTimeoutJob;
use Illuminate\Console\Command;

class TestStoppingTimeout extends Command
{
    protected $signature = 'test:stopping-timeout';
    protected $description = 'Test the stopping timeout job';

    public function handle()
    {
        $this->info('🧪 Testing HandleStoppingTimeoutJob');
        $this->line('');

        // Run the job directly (not through queue)
        $job = new HandleStoppingTimeoutJob();
        $job->handle();

        $this->line('');
        $this->info('✅ Job completed. Check logs for details.');
    }
}
