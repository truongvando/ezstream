<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;

class QuickStreamFix extends Command
{
    protected $signature = 'fix:stream {stream_id} {--status=STREAMING} {--vps=24}';
    protected $description = 'Quick fix for stream status';

    public function handle()
    {
        $streamId = $this->argument('stream_id');
        $status = $this->option('status');
        $vpsId = $this->option('vps');
        
        $this->info("Fixing Stream #{$streamId}");

        $stream = StreamConfiguration::find($streamId);
        
        if (!$stream) {
            $this->error("Stream not found");
            return 1;
        }

        $oldStatus = $stream->status;
        
        $stream->update([
            'status' => $status,
            'vps_server_id' => $vpsId,
            'last_status_update' => now(),
            'error_message' => null
        ]);

        $this->info("Updated: {$oldStatus} → {$status}");
        
        return 0;
    }
}
