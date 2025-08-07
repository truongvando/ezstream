<?php

namespace App\Console\Commands;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Services\AutoDeleteVideoService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AutoDeleteVideosCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'videos:auto-delete 
                            {--stream-id= : Delete videos for specific stream ID}
                            {--user-id= : Delete videos for specific user ID}
                            {--older-than= : Delete videos older than X days}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force delete even if stream is still active}
                            {--process-scheduled : Process all scheduled deletions}';

    /**
     * The console command description.
     */
    protected $description = 'Auto-delete videos from Bunny Stream and CDN based on various criteria';

    private AutoDeleteVideoService $autoDeleteService;

    public function __construct(AutoDeleteVideoService $autoDeleteService)
    {
        parent::__construct();
        $this->autoDeleteService = $autoDeleteService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🗑️ Auto Delete Videos Command Started');
        $this->info('Time: ' . now()->toDateTimeString());
        $this->newLine();

        // Process scheduled deletions
        if ($this->option('process-scheduled')) {
            return $this->processScheduledDeletions();
        }

        // Get videos to delete based on criteria
        $videos = $this->getVideosToDelete();

        if ($videos->isEmpty()) {
            $this->info('✅ No videos found matching the criteria');
            return 0;
        }

        $this->info("📊 Found {$videos->count()} videos matching criteria:");
        $this->newLine();

        // Display videos table
        $this->displayVideosTable($videos);

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No videos will be deleted');
            return 0;
        }

        // Confirm deletion
        if (!$this->option('force') && !$this->confirm('Do you want to proceed with deletion?')) {
            $this->info('❌ Deletion cancelled');
            return 0;
        }

        // Process deletions
        return $this->processVideoDeletions($videos);
    }

    /**
     * Process scheduled deletions
     */
    private function processScheduledDeletions(): int
    {
        $this->info('🔄 Processing scheduled deletions...');
        
        $result = $this->autoDeleteService->processScheduledDeletions();
        
        if ($result['success']) {
            $this->info("✅ Processed {$result['processed']} files");
            $this->info("🗑️ Successfully deleted {$result['deleted']} files");
            
            if (!empty($result['errors'])) {
                $this->warn("⚠️ Errors encountered:");
                foreach ($result['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }
            
            return 0;
        } else {
            $this->error("❌ Failed to process scheduled deletions: {$result['error']}");
            return 1;
        }
    }

    /**
     * Get videos to delete based on command options
     */
    private function getVideosToDelete()
    {
        $query = UserFile::where('status', '!=', 'DELETED');

        // Filter by stream ID
        if ($streamId = $this->option('stream-id')) {
            $stream = StreamConfiguration::find($streamId);
            if (!$stream) {
                $this->error("❌ Stream {$streamId} not found");
                exit(1);
            }
            
            $fileIds = collect($stream->video_source_path)->pluck('file_id')->toArray();
            $query->whereIn('id', $fileIds);
        }

        // Filter by user ID
        if ($userId = $this->option('user-id')) {
            $query->where('user_id', $userId);
        }

        // Filter by age
        if ($olderThan = $this->option('older-than')) {
            $cutoffDate = Carbon::now()->subDays((int)$olderThan);
            $query->where('created_at', '<', $cutoffDate);
        }

        // Only include files marked for auto-deletion or force mode
        if (!$this->option('force')) {
            $query->where('auto_delete_after_stream', true);
        }

        return $query->with(['user'])->get();
    }

    /**
     * Display videos in a table format
     */
    private function displayVideosTable($videos)
    {
        $tableData = $videos->map(function ($video) {
            return [
                'ID' => $video->id,
                'Filename' => $video->original_name,
                'User' => $video->user->name ?? 'N/A',
                'Size' => $this->formatBytes($video->size),
                'Disk' => $video->disk,
                'Auto Delete' => $video->auto_delete_after_stream ? '✅' : '❌',
                'Scheduled' => $video->scheduled_deletion_at ? 
                    $video->scheduled_deletion_at->format('Y-m-d H:i') : 'No',
                'Created' => $video->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table([
            'ID', 'Filename', 'User', 'Size', 'Disk', 'Auto Delete', 'Scheduled', 'Created'
        ], $tableData);

        $this->newLine();
    }

    /**
     * Process video deletions
     */
    private function processVideoDeletions($videos): int
    {
        $this->info('🗑️ Starting deletion process...');
        $this->newLine();

        $deleted = 0;
        $failed = 0;
        $totalSize = 0;

        $progressBar = $this->output->createProgressBar($videos->count());
        $progressBar->start();

        foreach ($videos as $video) {
            $progressBar->advance();
            
            try {
                // Check if video is still in use (unless force mode)
                if (!$this->option('force')) {
                    $activeStreams = StreamConfiguration::whereJsonContains('video_source_path', [['file_id' => $video->id]])
                        ->whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
                        ->count();
                        
                    if ($activeStreams > 0) {
                        $this->newLine();
                        $this->warn("⚠️ Skipping {$video->original_name} - still in use by {$activeStreams} active stream(s)");
                        continue;
                    }
                }

                $result = $this->autoDeleteService->deleteVideoFromAllSources($video);
                
                if ($result['success']) {
                    $deleted++;
                    $totalSize += $video->size;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->error("❌ Failed to delete {$video->original_name}: {$result['error']}");
                }

            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("❌ Exception deleting {$video->original_name}: {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📊 Deletion Summary:');
        $this->info("✅ Successfully deleted: {$deleted} videos");
        $this->info("❌ Failed: {$failed} videos");
        $this->info("💾 Total space freed: " . $this->formatBytes($totalSize));

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
