<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Services\BunnyStreamService;

class CheckVideoStatus extends Command
{
    protected $signature = 'check:video-status {video-id}';
    protected $description = 'Check video status comparison between API and Database';

    public function handle()
    {
        $videoId = $this->argument('video-id');
        
        $this->info("🔍 Checking Video Status: {$videoId}");
        $this->line(str_repeat('=', 60));

        // 1. Find file in database
        $file = UserFile::where('stream_video_id', $videoId)->first();
        
        if (!$file) {
            $this->error("❌ File not found in database for video ID: {$videoId}");
            return;
        }

        $this->info("📁 File Found:");
        $this->line("  Name: {$file->original_name}");
        $this->line("  File ID: {$file->id}");
        $this->line("  Created: {$file->created_at}");
        $this->line("  File Status: {$file->status}");

        // 2. Get database status
        $dbStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
        $this->line("  DB Processing Status: {$dbStatus}");

        // 3. Get API status
        $this->info("\n🌐 API Status:");
        try {
            $bunnyService = app(BunnyStreamService::class);
            $apiResult = $bunnyService->getVideoStatus($videoId);
            
            if ($apiResult['success']) {
                $apiStatus = $apiResult['status'];
                $numericStatus = $apiResult['numeric_status'];
                $progress = $apiResult['encoding_progress'];
                
                $this->line("  API Status: {$apiStatus}");
                $this->line("  Numeric: {$numericStatus}");
                $this->line("  Progress: {$progress}%");
                
                // 4. Compare
                $this->info("\n🔄 Comparison:");
                $this->line("  Database: {$dbStatus}");
                $this->line("  API: {$apiStatus}");
                
                if ($apiStatus === $dbStatus) {
                    $this->info("  ✅ Status MATCH");
                } else {
                    $this->warn("  ⚠️  Status MISMATCH!");
                    
                    if (in_array($apiStatus, ['finished', 'completed'])) {
                        $this->info("  💡 Video is ready but database not updated!");
                        $this->line("  This is the root cause of the issue.");
                    }
                }
                
            } else {
                $this->error("❌ API Error: " . $apiResult['error']);
            }
            
        } catch (\Exception $e) {
            $this->error("💥 Exception: " . $e->getMessage());
        }

        // 5. Check waiting streams
        $this->info("\n🎬 Related Streams:");
        $waitingStreams = StreamConfiguration::where('status', 'waiting_for_processing')
            ->where(function($query) use ($file) {
                $query->where('user_file_id', $file->id)
                      ->orWhereJsonContains('video_source_path', [['file_id' => $file->id]]);
            })
            ->get();

        if ($waitingStreams->count() > 0) {
            $this->warn("  ⏳ {$waitingStreams->count()} streams waiting for this video:");
            foreach ($waitingStreams as $stream) {
                $this->line("    Stream #{$stream->id}: {$stream->title}");
            }
            
            $this->info("\n💡 Solution:");
            $this->line("  Run CheckVideoProcessingJob to fix:");
            $this->line("  php artisan tinker --execute=\"App\\Jobs\\CheckVideoProcessingJob::dispatch({$file->id})\"");
            
        } else {
            $this->info("  ✅ No streams waiting for this video");
        }

        $this->line(str_repeat('=', 60));
        $this->info("✅ Check completed!");
    }
}
