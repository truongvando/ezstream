<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RestartVpsAgents extends Command
{
    protected $signature = 'vps:restart-agents {--force : Force restart all agents}';
    protected $description = 'Restart all VPS agents to apply new settings';

    public function handle(): int
    {
        $this->info('🔄 Restarting VPS agents to apply new settings...');

        $activeVpsServers = VpsServer::where('is_active', true)
            ->where('status', 'ACTIVE')
            ->get();

        if ($activeVpsServers->isEmpty()) {
            $this->warn('No active VPS servers found.');
            return 0;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($activeVpsServers as $vps) {
            $this->line("→ Restarting agent on VPS #{$vps->id} ({$vps->name})...");

            try {
                // Send restart command to agent
                $command = [
                    'command' => 'RESTART_AGENT',
                    'reason' => 'Deploy update - applying new heartbeat settings',
                    'timestamp' => time()
                ];

                $channel = "vps-commands:{$vps->id}";
                $result = Redis::publish($channel, json_encode($command));

                if ($result > 0) {
                    $this->info("  ✅ Restart command sent (subscribers: {$result})");
                    $successCount++;
                } else {
                    $this->warn("  ⚠️ No subscribers listening on channel {$channel}");
                    $failedCount++;
                }

                Log::info("VPS agent restart command sent", [
                    'vps_id' => $vps->id,
                    'vps_name' => $vps->name,
                    'subscribers' => $result
                ]);

            } catch (\Exception $e) {
                $this->error("  ❌ Failed to send restart command: {$e->getMessage()}");
                $failedCount++;

                Log::error("Failed to restart VPS agent", [
                    'vps_id' => $vps->id,
                    'vps_name' => $vps->name,
                    'error' => $e->getMessage()
                ]);
            }

            // Small delay between commands
            usleep(500000); // 0.5 seconds
        }

        $this->newLine();
        $this->info("📊 Restart Summary:");
        $this->line("  • Success: {$successCount}");
        $this->line("  • Failed: {$failedCount}");
        $this->line("  • Total: " . ($successCount + $failedCount));

        if ($successCount > 0) {
            $this->info("✅ Agent restart commands sent successfully!");
            $this->line("   Agents will restart and apply new heartbeat interval (5s)");
        }

        if ($failedCount > 0) {
            $this->warn("⚠️ Some agents may need manual restart");
        }

        return $failedCount > 0 ? 1 : 0;
    }
}
