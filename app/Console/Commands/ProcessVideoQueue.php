<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessVideoQueue extends Command
{
    protected $signature = 'video:process-queue {--once : Process only one job}';
    protected $description = 'Process video processing queue';

    public function handle()
    {
        $this->info('🎬 Processing video processing queue...');
        
        if ($this->option('once')) {
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'video-processing',
                '--once' => true,
                '--timeout' => 60
            ]);
        } else {
            $exitCode = Artisan::call('queue:work', [
                '--queue' => 'video-processing',
                '--sleep' => 3,
                '--tries' => 3,
                '--timeout' => 60
            ]);
        }
        
        $this->info('✅ Video queue processing completed');
        return $exitCode;
    }
}
