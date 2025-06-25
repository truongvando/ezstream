<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Jobs\StartStreamJob;

class StreamTestCommand extends Command
{
    protected $signature = 'stream:test 
                            {vps_id : VPS Server ID} 
                            {--file_id= : Google Drive File ID}
                            {--stream_key= : RTMP Stream Key}
                            {--platform=youtube : Streaming platform (youtube/facebook/custom)}';

    protected $description = 'Test streaming through the system to a VPS';

    public function handle()
    {
        $vpsId = $this->argument('vps_id');
        $fileId = $this->option('file_id') ?? '1234567890abcdef'; // Default test file
        $streamKey = $this->option('stream_key') ?? 'test-stream-key';
        $platform = $this->option('platform');
        
        // Get VPS
        $vps = VpsServer::find($vpsId);
        if (!$vps || $vps->status !== 'ACTIVE') {
            $this->error('VPS not found or not active');
            return 1;
        }
        
        $this->info("Using VPS: {$vps->name} ({$vps->ip_address})");
        
        // Create or get test file
        $userFile = UserFile::firstOrCreate([
            'user_id' => 1, // Admin user
            'google_drive_file_id' => $fileId
        ], [
            'original_name' => 'test_stream_' . $fileId . '.mp4',
            'disk' => 'google_drive',
            'path' => 'google_drive',
            'mime_type' => 'video/mp4',
            'size' => 100 * 1024 * 1024, // 100MB estimate
            'status' => 'AVAILABLE',
            'download_source' => 'google_drive',
            'source_url' => "https://drive.google.com/file/d/{$fileId}/view"
        ]);
        
        $this->info("Using file: {$userFile->original_name}");
        
        // Platform URLs
        $platformUrls = [
            'youtube' => 'rtmp://a.rtmp.youtube.com/live2',
            'facebook' => 'rtmp://live-api-s.facebook.com/rtmp',
            'custom' => 'rtmp://localhost/live'
        ];
        
        $rtmpUrl = $platformUrls[$platform] ?? $platformUrls['youtube'];
        
        // Create stream configuration
        $stream = StreamConfiguration::create([
            'user_id' => 1,
            'title' => 'System Test Stream - ' . now()->format('Y-m-d H:i:s'),
            'description' => "Testing on VPS: {$vps->name}",
            'vps_server_id' => $vps->id,
            'user_file_id' => $userFile->id,
            'video_source_path' => 'google_drive',
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $streamKey,
            'status' => 'INACTIVE',
            'stream_preset' => 'optimized',
            'loop' => true
        ]);
        
        $this->info("Created stream configuration ID: {$stream->id}");
        
        // Ask for confirmation
        if ($this->confirm('Start streaming now?')) {
            $this->info('Dispatching StartStreamJob...');
            StartStreamJob::dispatch($stream);
            
            $this->info('Stream job dispatched!');
            $this->info('');
            $this->info('Monitor stream at: /admin/streams');
            $this->info("RTMP URL: {$rtmpUrl}/{$streamKey}");
            $this->info('');
            $this->table(
                ['Property', 'Value'],
                [
                    ['VPS', "{$vps->name} ({$vps->ip_address})"],
                    ['File', $userFile->original_name],
                    ['Google Drive ID', $fileId],
                    ['Stream ID', $stream->id],
                    ['RTMP URL', $rtmpUrl],
                    ['Stream Key', $streamKey],
                    ['Preset', 'optimized'],
                    ['Loop', 'Yes']
                ]
            );
        } else {
            $this->warn('Stream created but not started. Start manually from admin panel.');
        }
        
        return 0;
    }
} 