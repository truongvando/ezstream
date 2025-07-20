<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Jobs\StopMultistreamJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TestStopStreamFix extends Command
{
    protected $signature = 'test:stop-stream-fix {stream_id?}';
    protected $description = 'Test the stop stream fix for Laravel-Agent communication';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        
        if ($streamId) {
            $this->testSpecificStream($streamId);
        } else {
            $this->testAllActiveStreams();
        }
    }

    private function testSpecificStream($streamId)
    {
        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("Stream #{$streamId} not found");
            return;
        }

        $this->info("🧪 Testing stop stream fix for Stream #{$streamId}");
        $this->info("Current status: {$stream->status}");
        $this->info("VPS ID: {$stream->vps_server_id}");

        if (!in_array($stream->status, ['STREAMING', 'STARTING'])) {
            $this->warn("Stream is not in STREAMING/STARTING status, cannot test stop");
            return;
        }

        // Monitor Redis traffic
        $this->info("📡 Monitoring Redis traffic for 30 seconds...");
        $this->monitorRedisTraffic($streamId, 30);

        // Send stop command
        $this->info("🛑 Sending stop command...");
        StopMultistreamJob::dispatch($stream);

        // Monitor for another 30 seconds
        $this->info("📡 Monitoring response for 30 seconds...");
        $this->monitorRedisTraffic($streamId, 30);

        // Check final status
        $stream->refresh();
        $this->info("Final status: {$stream->status}");
    }

    private function testAllActiveStreams()
    {
        $activeStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
            ->get();

        if ($activeStreams->isEmpty()) {
            $this->info("No active streams to test");
            return;
        }

        $this->info("🧪 Testing stop stream fix for {$activeStreams->count()} active streams");

        foreach ($activeStreams as $stream) {
            $this->info("Stream #{$stream->id}: {$stream->status} (VPS: {$stream->vps_server_id})");
        }

        if (!$this->confirm('Do you want to stop all these streams for testing?')) {
            return;
        }

        foreach ($activeStreams as $stream) {
            if (in_array($stream->status, ['STREAMING', 'STARTING'])) {
                $this->info("🛑 Stopping Stream #{$stream->id}");
                StopMultistreamJob::dispatch($stream);
                sleep(1); // Small delay between commands
            }
        }

        $this->info("✅ All stop commands sent. Monitor logs for results.");
    }

    private function monitorRedisTraffic($streamId, $seconds)
    {
        $redis = Redis::connection();
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe('stream-status');

        $startTime = time();
        $this->info("Listening to stream-status channel...");

        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                $data = json_decode($message->payload, true);
                
                if (isset($data['stream_id']) && $data['stream_id'] == $streamId) {
                    $status = $data['status'] ?? 'N/A';
                    $msg = $data['message'] ?? 'N/A';
                    $this->line("📨 Stream #{$streamId}: {$status} - {$msg}");
                }
            }

            if ((time() - $startTime) > $seconds) {
                break;
            }
        }

        $pubsub->unsubscribe();
    }
}
