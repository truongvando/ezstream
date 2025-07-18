<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ForceHeartbeatSync extends Command
{
    protected $signature = 'heartbeat:force-sync {vps_id} {stream_ids*} {--status=STREAMING}';
    protected $description = 'Force sync streams based on simulated heartbeat';

    public function handle()
    {
        $vpsId = $this->argument('vps_id');
        $streamIds = $this->argument('stream_ids');
        $status = $this->option('status');

        $this->info("🔥 [ForceSync] Simulating heartbeat from VPS #{$vpsId} for streams: " . implode(', ', $streamIds));

        $syncedCount = 0;
        $errorCount = 0;

        foreach ($streamIds as $streamId) {
            try {
                $stream = StreamConfiguration::find($streamId);
                
                if (!$stream) {
                    $this->error("❌ Stream #{$streamId} not found");
                    $errorCount++;
                    continue;
                }

                $oldStatus = $stream->status;
                $oldVpsId = $stream->vps_server_id;

                $this->info("📊 Stream #{$streamId} - Current: {$oldStatus} (VPS: {$oldVpsId}) → Force: {$status} (VPS: {$vpsId})");

                if ($status === 'STREAMING') {
                    // Force sync to STREAMING regardless of current status
                    $updateData = [
                        'status' => 'STREAMING',
                        'last_status_update' => now(),
                        'vps_server_id' => $vpsId,
                        'error_message' => null,
                        'last_started_at' => $stream->last_started_at ?: now(),
                        'process_id' => rand(100000, 999999) // Simulate PID
                    ];

                    $stream->update($updateData);

                    // Create progress update
                    StreamProgressService::createStageProgress(
                        $streamId, 
                        'streaming', 
                        "🔥 FORCE SYNC: Stream đang phát trực tiếp! (VPS #{$vpsId})"
                    );

                    $this->info("✅ [ForceSync] Stream #{$streamId}: {$oldStatus} → STREAMING (VPS: {$oldVpsId} → {$vpsId})");
                    $syncedCount++;

                } else {
                    $this->warn("⚠️ [ForceSync] Unsupported status: {$status}");
                    $errorCount++;
                }

            } catch (\Exception $e) {
                $this->error("❌ [ForceSync] Failed to sync stream #{$streamId}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->info("🎯 [ForceSync] Completed: {$syncedCount} synced, {$errorCount} errors");

        // Show final status
        $this->info("📊 Final Status:");
        foreach ($streamIds as $streamId) {
            $stream = StreamConfiguration::find($streamId);
            if ($stream) {
                $heartbeatAge = $stream->last_status_update ? $stream->last_status_update->diffForHumans() : 'Never';
                $this->line("  Stream #{$streamId}: {$stream->status} (VPS: {$stream->vps_server_id}, Heartbeat: {$heartbeatAge})");
            }
        }

        return 0;
    }
}
