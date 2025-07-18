<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugStreamSync extends Command
{
    protected $signature = 'debug:stream-sync {stream_id}';
    protected $description = 'Debug stream sync issues for specific stream';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        
        $this->info("🔍 [Debug] Analyzing Stream #{$streamId}");

        // 1. Check if stream exists
        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("❌ Stream #{$streamId} not found in database");
            return 1;
        }

        // 2. Display current status
        $this->info("📊 Current Stream Status:");
        $this->line("  ID: {$stream->id}");
        $this->line("  Title: {$stream->title}");
        $this->line("  Status: {$stream->status}");
        $this->line("  VPS ID: " . ($stream->vps_server_id ?: 'NULL'));
        $this->line("  Process ID: " . ($stream->process_id ?: 'NULL'));
        $this->line("  User ID: {$stream->user_id}");
        $this->line("  Last Status Update: " . ($stream->last_status_update ?: 'NULL'));
        $this->line("  Last Started At: " . ($stream->last_started_at ?: 'NULL'));
        $this->line("  Error Message: " . ($stream->error_message ?: 'NULL'));

        // 3. Test database connection
        $this->info("🔗 Testing Database Connection:");
        try {
            $result = DB::select('SELECT NOW() as current_time');
            $this->info("  ✅ Database connection OK: " . $result[0]->current_time);
        } catch (\Exception $e) {
            $this->error("  ❌ Database connection failed: {$e->getMessage()}");
            return 1;
        }

        // 4. Test update capability
        $this->info("🧪 Testing Update Capability:");
        try {
            $originalUpdatedAt = $stream->updated_at;
            $stream->touch(); // Update timestamp
            $stream->refresh();
            
            if ($stream->updated_at != $originalUpdatedAt) {
                $this->info("  ✅ Stream update capability OK");
            } else {
                $this->error("  ❌ Stream update failed - timestamp not changed");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Stream update failed: {$e->getMessage()}");
        }

        // 5. Simulate heartbeat sync
        $this->info("🔥 Simulating Heartbeat Sync:");
        try {
            $oldStatus = $stream->status;
            
            $updateData = [
                'status' => 'STREAMING',
                'last_status_update' => now(),
                'vps_server_id' => 24,
                'error_message' => null,
                'process_id' => 722503
            ];
            
            $this->info("  📝 Update data: " . json_encode($updateData));
            
            $result = $stream->update($updateData);
            
            if ($result) {
                $this->info("  ✅ Simulated sync successful");
                
                // Verify
                $stream->refresh();
                $this->info("  🔍 Verification:");
                $this->line("    Status: {$oldStatus} → {$stream->status}");
                $this->line("    VPS ID: {$stream->vps_server_id}");
                $this->line("    Process ID: {$stream->process_id}");
                $this->line("    Last Update: {$stream->last_status_update}");
                
                // Test progress service
                try {
                    StreamProgressService::createStageProgress($streamId, 'streaming', 'Debug test sync');
                    $this->info("  ✅ Progress service OK");
                } catch (\Exception $e) {
                    $this->error("  ❌ Progress service failed: {$e->getMessage()}");
                }
                
            } else {
                $this->error("  ❌ Simulated sync failed");
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Simulation failed: {$e->getMessage()}");
        }

        // 6. Check Redis connection
        $this->info("🔴 Testing Redis Connection:");
        try {
            $redis = app('redis')->connection();
            $redis->ping();
            $this->info("  ✅ Redis connection OK");
            
            // Test publish
            $result = $redis->publish('test-channel', 'test-message');
            $this->info("  ✅ Redis publish OK (subscribers: {$result})");
            
        } catch (\Exception $e) {
            $this->error("  ❌ Redis connection failed: {$e->getMessage()}");
        }

        return 0;
    }
}
