<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;

class FixWaitingStreams extends Command
{
    protected $signature = 'stream:fix-waiting {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix streams stuck in waiting_for_processing status';

    public function handle()
    {
        $this->info('🔍 Finding streams waiting for processing...');
        
        $waitingStreams = StreamConfiguration::where('status', 'waiting_for_processing')
            ->with('userFile')
            ->get();
            
        if ($waitingStreams->isEmpty()) {
            $this->info('✅ No waiting streams found!');
            return 0;
        }
        
        $this->info("Found {$waitingStreams->count()} waiting streams:");
        
        $fixed = 0;
        $errors = 0;
        
        foreach ($waitingStreams as $stream) {
            $this->line("🎬 Stream #{$stream->id}: {$stream->title}");
            
            try {
                $file = $stream->userFile;
                
                if (!$file) {
                    $this->error("  ❌ No associated file found");
                    $errors++;
                    continue;
                }
                
                $fileStatus = $file->status;
                $processingStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
                
                $this->line("  📁 File: {$file->original_name}");
                $this->line("  📊 File status: {$fileStatus}");
                $this->line("  🎬 Processing status: {$processingStatus}");
                
                if ($this->option('dry-run')) {
                    if (in_array($fileStatus, ['ready', 'uploaded']) || 
                        in_array($processingStatus, ['finished', 'completed', 'ready'])) {
                        $this->line("  🔧 Would fix: Set stream to INACTIVE");
                    } else {
                        $this->line("  ⏳ Would keep waiting: File not ready yet");
                    }
                } else {
                    if (in_array($fileStatus, ['ready', 'uploaded']) || 
                        in_array($processingStatus, ['finished', 'completed', 'ready'])) {
                        
                        // File is ready, update stream to INACTIVE
                        $stream->update(['status' => 'INACTIVE']);
                        
                        $this->info("  ✅ Fixed: Set to INACTIVE");
                        $fixed++;
                    } else {
                        $this->warn("  ⏳ Keeping waiting: File not ready yet");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  💥 Error: " . $e->getMessage());
                $errors++;
            }
        }
        
        if ($this->option('dry-run')) {
            $this->info("\n🔍 Dry run completed. Use --no-dry-run to apply fixes.");
        } else {
            $this->info("\n✅ Fixed: {$fixed} streams");
            if ($errors > 0) {
                $this->warn("⚠️  Errors: {$errors} streams");
            }
        }
        
        return 0;
    }
}
