<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorStreamStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Monitor all streams and update their real status
     */
    public function handle(SshService $sshService): void
    {
        Log::info("Starting stream status monitoring");

        // Get all streams that claim to be streaming
        $streamingStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])->get();

        foreach ($streamingStreams as $stream) {
            $this->checkStreamStatus($stream, $sshService);
        }

        Log::info("Stream status monitoring completed", [
            'checked_streams' => $streamingStreams->count()
        ]);
    }

    private function checkStreamStatus(StreamConfiguration $stream, SshService $sshService): void
    {
        try {
            if (!$stream->vps_server_id) {
                Log::warning("Stream has no VPS assigned", ['stream_id' => $stream->id]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'No VPS assigned']);
                return;
            }

            $vps = VpsServer::find($stream->vps_server_id);
            if (!$vps || $vps->status !== 'ACTIVE') {
                Log::warning("Stream VPS is not active", [
                    'stream_id' => $stream->id,
                    'vps_id' => $stream->vps_server_id,
                    'vps_status' => $vps->status ?? 'NOT_FOUND'
                ]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'VPS not active']);
                return;
            }

            if (!$sshService->connect($vps)) {
                Log::warning("Cannot connect to stream VPS", [
                    'stream_id' => $stream->id,
                    'vps_ip' => $vps->ip_address
                ]);
                $stream->update(['status' => 'ERROR', 'error_message' => 'Cannot connect to VPS']);
                return;
            }

            // Check if FFmpeg is actually running for this stream
            $ffmpegCheck = $sshService->execute("ps aux | grep 'stream_{$stream->id}' | grep ffmpeg | grep -v grep | wc -l");
            $isStreaming = trim($ffmpegCheck) > 0;

            // Check if stream directory exists
            $streamDir = "/tmp/stream_{$stream->id}";
            $dirExists = $sshService->execute("[ -d '{$streamDir}' ] && echo 'YES' || echo 'NO'");
            $hasDirctory = trim($dirExists) === 'YES';

            // Check for failed jobs
            $failedJobs = $sshService->execute("ls /opt/job-queue/failed/ | grep job_{$stream->id} | wc -l");
            $hasFailedJobs = trim($failedJobs) > 0;

            $sshService->disconnect();

            // Determine real status
            if ($isStreaming && $hasDirctory) {
                // Actually streaming
                if ($stream->status !== 'STREAMING') {
                    Log::info("Stream status corrected to STREAMING", ['stream_id' => $stream->id]);
                    $stream->update(['status' => 'STREAMING', 'error_message' => null]);
                }
            } else if ($hasFailedJobs) {
                // Has failed jobs - likely download issues
                Log::warning("Stream has failed jobs", ['stream_id' => $stream->id]);
                $stream->update([
                    'status' => 'ERROR', 
                    'error_message' => 'Stream failed - check download URLs or file availability'
                ]);
            } else if ($stream->status === 'STREAMING') {
                // Claims to be streaming but isn't
                Log::warning("Stream claims STREAMING but not actually streaming", ['stream_id' => $stream->id]);
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => 'Stream process not found - may have crashed'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error monitoring stream status", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
