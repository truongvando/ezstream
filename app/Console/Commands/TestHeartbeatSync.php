<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestHeartbeatSync extends Command
{
    protected $signature = 'test:heartbeat-sync {stream_id} {vps_id} {--status=STREAMING}';
    protected $description = 'Test heartbeat sync for a specific stream';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        $vpsId = $this->argument('vps_id');
        $heartbeatStatus = $this->option('status');

        $this->info("🧪 [TestHeartbeat] Testing sync for Stream #{$streamId} on VPS #{$vpsId}");

        // Find the stream
        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("❌ Stream #{$streamId} not found in database");
            return 1;
        }

        $oldStatus = $stream->status;
        $this->info("📊 Current stream status: {$oldStatus}");
        $this->info("💓 Heartbeat reports status: {$heartbeatStatus}");

        // Simulate heartbeat sync logic
        if ($heartbeatStatus === 'STREAMING' && in_array($oldStatus, ['STARTING', 'INACTIVE', 'ERROR', 'STOPPING'])) {
            $this->info("✅ [TestHeartbeat] Syncing stream #{$streamId}: {$oldStatus} → STREAMING");
            
            $updateData = [
                'status' => 'STREAMING',
                'last_status_update' => now(),
                'vps_server_id' => $vpsId,
                'error_message' => null,
                'last_started_at' => $stream->last_started_at ?: now()
            ];
            
            $stream->update($updateData);
            
            // Create progress update for UI
            StreamProgressService::createStageProgress($streamId, 'streaming', "Stream đang phát trực tiếp! (Test sync from VPS #{$vpsId})");
            
            $this->info("🎯 [TestHeartbeat] Successfully synced stream #{$streamId} to STREAMING");
            
            // Show updated stream info
            $stream->refresh();
            $this->info("📊 Updated stream status: {$stream->status}");
            $this->info("🕐 Last status update: {$stream->last_status_update}");
            $this->info("🖥️ VPS ID: {$stream->vps_server_id}");
            
        } elseif ($heartbeatStatus === 'STREAMING' && $oldStatus === 'STREAMING') {
            $this->info("🔄 [TestHeartbeat] Stream already STREAMING, updating timestamp");
            $stream->update([
                'last_status_update' => now(),
                'vps_server_id' => $vpsId
            ]);
            
        } else {
            $this->info("ℹ️ [TestHeartbeat] No action needed - DB: {$oldStatus}, Heartbeat: {$heartbeatStatus}");
        }

        return 0;
    }
}
