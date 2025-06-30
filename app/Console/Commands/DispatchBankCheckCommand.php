<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckBankTransactionsJob;

class DispatchBankCheckCommand extends Command
{
    protected $signature = 'dispatch:bank-check';
    protected $description = 'Dispatch CheckBankTransactionsJob to queue';

    public function handle()
    {
        $this->info('🚀 Dispatching CheckBankTransactionsJob to queue...');
        
        CheckBankTransactionsJob::dispatch();
        
        $this->info('✅ Job dispatched to queue! Run "php artisan queue:work" to process it.');
    }
} 