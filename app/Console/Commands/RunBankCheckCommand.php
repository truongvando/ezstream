<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckBankTransactionsJob;

class RunBankCheckCommand extends Command
{
    protected $signature = 'run:bank-check';
    protected $description = 'Force run CheckBankTransactionsJob';

    public function handle()
    {
        $this->info('🚀 Running CheckBankTransactionsJob...');
        
        $job = new CheckBankTransactionsJob();
        $job->handle();
        
        $this->info('✅ Job completed!');
    }
} 