<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyStreamsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streams:verify-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify status of active streams and clean up zombie processes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verifying stream statuses...');
        
        $activeStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
            ->with('vpsServer')
            ->get();

        if ($activeStreams->isEmpty()) {
            $this->info('No active streams found.');
            return 0;
        }

        $sshService = new SshService();
        $fixed = 0;
        $errors = 0;

        foreach ($activeStreams as $stream) {
            $this->line("Checking stream {$stream->id}: {$stream->title}");
            
            try {
                if (!$stream->vpsServer) {
                    $this->warn("  ⚠️  No VPS assigned, marking as ERROR");
                    $stream->update(['status' => 'ERROR', 'output_log' => 'No VPS server assigned']);
                    $fixed++;
                    continue;
                }

                if (!$sshService->connect($stream->vpsServer)) {
                    $this->error("  ❌ Cannot connect to VPS {$stream->vpsServer->name}");
                    $errors++;
                    continue;
                }

                // Check if stream directory exists
                $streamDir = "/tmp/stream_{$stream->id}";
                $dirExists = $sshService->execute("test -d {$streamDir} && echo 'EXISTS' || echo 'MISSING'");
                
                if (trim($dirExists) === 'MISSING') {
                    $this->warn("  ⚠️  Stream directory missing, marking as STOPPED");
                    $stream->update([
                        'status' => 'STOPPED',
                        'output_log' => 'Stream directory not found on VPS',
                        'last_stopped_at' => now(),
                        'ffmpeg_pid' => null
                    ]);
                    $fixed++;
                    $sshService->disconnect();
                    continue;
                }

                // Check if FFmpeg process is running
                if ($stream->ffmpeg_pid) {
                    $processExists = $sshService->execute("ps -p {$stream->ffmpeg_pid} > /dev/null && echo 'RUNNING' || echo 'DEAD'");
                    
                    if (trim($processExists) === 'DEAD') {
                        $this->warn("  ⚠️  FFmpeg process {$stream->ffmpeg_pid} is dead");
                        
                        // Check logs for completion or error
                        $logFile = "{$streamDir}/stream.log";
                        $logExists = $sshService->execute("test -f {$logFile} && echo 'EXISTS' || echo 'MISSING'");
                        
                        if (trim($logExists) === 'EXISTS') {
                            $lastLog = $sshService->execute("tail -10 {$logFile}");
                            
                            if (strpos($lastLog, 'Conversion failed') !== false || strpos($lastLog, 'Error') !== false) {
                                $this->warn("    Found errors in log, marking as ERROR");
                                $stream->update([
                                    'status' => 'ERROR',
                                    'output_log' => 'Stream failed - check VPS logs',
                                    'last_stopped_at' => now(),
                                    'ffmpeg_pid' => null
                                ]);
                            } else {
                                $this->info("    No errors found, marking as COMPLETED");
                                $stream->update([
                                    'status' => 'COMPLETED',
                                    'output_log' => 'Stream completed successfully',
                                    'last_stopped_at' => now(),
                                    'ffmpeg_pid' => null
                                ]);
                            }
                        } else {
                            $this->warn("    No log file found, marking as ERROR");
                            $stream->update([
                                'status' => 'ERROR',
                                'output_log' => 'Stream process died without logs',
                                'last_stopped_at' => now(),
                                'ffmpeg_pid' => null
                            ]);
                        }
                        
                        $fixed++;
                    } else {
                        $this->info("  ✅ Stream is running correctly");
                    }
                }

                $sshService->disconnect();

            } catch (\Exception $e) {
                $this->error("  ❌ Error checking stream {$stream->id}: {$e->getMessage()}");
                $errors++;
                Log::error("Stream verification error", [
                    'stream_id' => $stream->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("🎉 Verification complete!");
        $this->table(['Metric', 'Count'], [
            ['Streams checked', $activeStreams->count()],
            ['Issues fixed', $fixed],
            ['Errors encountered', $errors]
        ]);

        return 0;
    }
}
