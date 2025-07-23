<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class SyncVpsStreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:sync {vps_id? : The ID of the VPS to sync. If not provided, all active VPS will be synced.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the state of streams between the database and a VPS agent.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vpsId = $this->argument('vps_id');

        if ($vpsId) {
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                $this->error("VPS with ID {$vpsId} not found.");
                return 1;
            }
            $this->syncVps($vps);
        } else {
            $this->info("Syncing all active VPS servers...");
            $vpsServers = VpsServer::where('status', 'active')->get();
            foreach ($vpsServers as $vps) {
                $this->syncVps($vps);
            }
        }

        $this->info('Sync command completed.');
        return 0;
    }

    protected function syncVps(VpsServer $vps)
    {
        $this->info("--- Syncing VPS #{$vps->id} ({$vps->ip_address}) ---");

        try {
            // 1. Get the desired state from the database (our source of truth)
            $expectedStreamIds = StreamConfiguration::where('vps_server_id', $vps->id)
                ->whereIn('status', ['STREAMING', 'STARTING'])
                ->pluck('id')
                ->toArray();

            $this->info("Database expects " . count($expectedStreamIds) . " streams: " . implode(', ', $expectedStreamIds ?: ['None']));

            // 2. Build the SYNC_STATE command
            $syncCommand = [
                'command' => 'SYNC_STATE',
                'vps_id' => $vps->id,
                'expected_streams' => $expectedStreamIds,
                'timestamp' => now()->timestamp,
            ];

            // 3. Publish the command to the specific VPS channel
            $channel = "vps-commands:{$vps->id}";
            $payload = json_encode($syncCommand);

            $subscribers = Redis::publish($channel, $payload);

            if ($subscribers > 0) {
                $this->info("✅ Sent SYNC_STATE command to {$channel}. Agent is listening.");
            } else {
                $this->warn("⚠️ Sent SYNC_STATE command to {$channel}, but no agent was listening. VPS might be offline or agent is down.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to sync VPS #{$vps->id}: " . $e->getMessage());
            Log::error("Failed to sync VPS #{$vps->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
} 