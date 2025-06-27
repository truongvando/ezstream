<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Jobs\SyncVpsStatsJob;
use Illuminate\Support\Facades\Log;

class SyncAllVpsStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:sync-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to sync stats for all active VPS servers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to dispatch VPS stats sync jobs...');
        Log::info('Scheduler is running vps:sync-stats command.');

        $activeServers = VpsServer::where('status', 'ACTIVE')->get();

        if ($activeServers->isEmpty()) {
            $this->info('No active VPS servers to sync.');
            return 0;
        }

        foreach ($activeServers as $server) {
            SyncVpsStatsJob::dispatch($server);
            $this->info("Dispatched job for VPS: {$server->name} (ID: {$server->id})");
        }

        $this->info('All VPS stats sync jobs have been dispatched successfully.');
        return 0;
    }
} 