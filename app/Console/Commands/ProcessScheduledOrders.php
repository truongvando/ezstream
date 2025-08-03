<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessScheduledOrdersJob;

class ProcessScheduledOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled and repeat orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing scheduled orders...');

        ProcessScheduledOrdersJob::dispatch();

        $this->info('Scheduled orders processing job dispatched.');
    }
}
