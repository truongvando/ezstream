<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BufferedStreamingService
{
    private $bufferPath = 'streaming_buffers/';
    private $chunkSize = 100 * 1024 * 1024; // 100MB per chunk
    private $maxBufferSize = 2 * 1024 * 1024 * 1024; // 2GB total buffer
    private $maxChunks = 20; // 20 chunks = 2GB
    
    public function __construct()
    {
        // Ensure buffer directory exists
        if (!Storage::disk('local')->exists($this->bufferPath)) {
            Storage::disk('local')->makeDirectory($this->bufferPath);
        }
    }

    /**
     * Start buffered streaming for a file
     */
    public function startBufferedStream($fileId, $streamingUrl, $rtmpTargets = [])
    {
        try {
            // Get optimized Google Drive URL
            $optimizedService = app(OptimizedStreamingService::class);
            $urlResult = $optimizedService->getOptimizedStreamingUrl($fileId);
            
            if (!$urlResult['success']) {
                return ['success' => false, 'error' => 'Cannot get streaming URL'];
            }
            
            $sourceUrl = $urlResult['url'];
            $bufferDir = $this->bufferPath . $fileId . '/';
            
            // Create buffer directory for this file
            Storage::disk('local')->makeDirectory($bufferDir);
            
            // Start background buffer process
            $this->startBufferProcess($fileId, $sourceUrl, $bufferDir);
            
            // Start streaming process
            $streamProcesses = [];
            foreach ($rtmpTargets as $target) {
                $streamProcesses[] = $this->startStreamProcess($bufferDir, $target);
            }
            
            return [
                'success' => true,
                'file_id' => $fileId,
                'buffer_directory' => $bufferDir,
                'source_url' => $sourceUrl,
                'stream_processes' => count($streamProcesses),
                'buffer_status' => $this->getBufferStatus($bufferDir),
                'estimated_buffer_time' => '2-5 minutes for 2GB buffer',
                'concurrent_streams_supported' => 'Unlimited from buffer'
            ];
            
        } catch (\Exception $e) {
            Log::error("Buffered streaming failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Start buffer process in background
     */
    private function startBufferProcess($fileId, $sourceUrl, $bufferDir)
    {
        $command = sprintf(
            'nohup php %s artisan stream:buffer "%s" "%s" "%s" > /dev/null 2>&1 &',
            base_path(),
            $fileId,
            $sourceUrl,
            $bufferDir
        );
        
        exec($command);
        Log::info("Started buffer process for file: {$fileId}");
    }

    /**
     * Download and manage buffer chunks
     */
    public function manageBuffer($fileId, $sourceUrl, $bufferDir)
    {
        $chunkIndex = 0;
        $totalDownloaded = 0;
        
        while (true) {
            try {
                $chunkFile = $bufferDir . "chunk_{$chunkIndex}.mp4";
                $startByte = $totalDownloaded;
                $endByte = $startByte + $this->chunkSize - 1;
                
                // Download chunk with range request
                $response = Http::timeout(60)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Range' => "bytes={$startByte}-{$endByte}"
                    ])
                    ->get($sourceUrl);
                
                if ($response->successful()) {
                    Storage::disk('local')->put($chunkFile, $response->body());
                    $totalDownloaded += strlen($response->body());
                    
                    Log::info("Downloaded chunk {$chunkIndex} for file {$fileId}");
                    
                    // Clean old chunks if buffer is full
                    $this->cleanOldChunks($bufferDir, $chunkIndex);
                    
                    $chunkIndex++;
                    
                    // Small delay to prevent overwhelming
                    usleep(100000); // 0.1 second
                } else {
                    Log::warning("Failed to download chunk {$chunkIndex} for file {$fileId}");
                    sleep(5); // Wait 5 seconds before retry
                }
                
            } catch (\Exception $e) {
                Log::error("Buffer management error: " . $e->getMessage());
                sleep(10); // Wait 10 seconds before retry
            }
        }
    }

    /**
     * Clean old chunks to maintain buffer size
     */
    private function cleanOldChunks($bufferDir, $currentChunk)
    {
        if ($currentChunk >= $this->maxChunks) {
            $oldChunk = $currentChunk - $this->maxChunks;
            $oldChunkFile = $bufferDir . "chunk_{$oldChunk}.mp4";
            
            if (Storage::disk('local')->exists($oldChunkFile)) {
                Storage::disk('local')->delete($oldChunkFile);
                Log::info("Cleaned old chunk: {$oldChunk}");
            }
        }
    }

    /**
     * Start streaming process from buffer
     */
    private function startStreamProcess($bufferDir, $rtmpTarget)
    {
        // Create playlist file for chunks
        $playlistFile = $bufferDir . 'playlist.m3u8';
        $this->createPlaylist($bufferDir, $playlistFile);
        
        $command = sprintf(
            'nohup ffmpeg -re -f hls -i "%s" ' .
            '-c:v libx264 -preset veryfast -tune zerolatency ' .
            '-b:v 2500k -maxrate 2500k -bufsize 5000k ' .
            '-r 30 -s 1280x720 ' .
            '-c:a aac -b:a 128k -ar 44100 ' .
            '-f flv "%s" > /dev/null 2>&1 &',
            $playlistFile,
            $rtmpTarget
        );
        
        exec($command);
        Log::info("Started stream process to: {$rtmpTarget}");
        
        return $rtmpTarget;
    }

    /**
     * Create HLS playlist from buffer chunks
     */
    private function createPlaylist($bufferDir, $playlistFile)
    {
        $playlist = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:300\n";
        
        // Add available chunks to playlist
        $chunks = Storage::disk('local')->files($bufferDir);
        sort($chunks);
        
        foreach ($chunks as $chunk) {
            if (strpos($chunk, 'chunk_') !== false) {
                $playlist .= "#EXTINF:300.0,\n";
                $playlist .= storage_path('app/' . $chunk) . "\n";
            }
        }
        
        Storage::disk('local')->put($playlistFile, $playlist);
    }

    /**
     * Get buffer status
     */
    public function getBufferStatus($bufferDir)
    {
        $chunks = Storage::disk('local')->files($bufferDir);
        $chunkCount = count(array_filter($chunks, fn($f) => strpos($f, 'chunk_') !== false));
        $totalSize = 0;
        
        foreach ($chunks as $chunk) {
            $totalSize += Storage::disk('local')->size($chunk);
        }
        
        return [
            'chunk_count' => $chunkCount,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'buffer_percentage' => round(($totalSize / $this->maxBufferSize) * 100, 2),
            'estimated_duration_minutes' => round($chunkCount * 5, 2) // Assuming 5 min per chunk
        ];
    }

    /**
     * Stop buffered streaming
     */
    public function stopBufferedStream($fileId)
    {
        try {
            // Kill buffer and stream processes
            exec("pkill -f 'stream:buffer {$fileId}'");
            exec("pkill -f 'ffmpeg.*{$fileId}'");
            
            // Clean buffer directory
            $bufferDir = $this->bufferPath . $fileId . '/';
            Storage::disk('local')->deleteDirectory($bufferDir);
            
            return [
                'success' => true,
                'message' => "Stopped buffered streaming for file: {$fileId}"
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all active buffered streams
     */
    public function getActiveStreams()
    {
        $bufferDirs = Storage::disk('local')->directories($this->bufferPath);
        $activeStreams = [];
        
        foreach ($bufferDirs as $dir) {
            $fileId = basename($dir);
            $status = $this->getBufferStatus($dir . '/');
            
            $activeStreams[] = [
                'file_id' => $fileId,
                'buffer_status' => $status,
                'buffer_directory' => $dir
            ];
        }
        
        return $activeStreams;
    }

    /**
     * Calculate optimal buffer settings based on VPS specs
     */
    public function calculateOptimalSettings($vpsCpuCores = 2, $vpsRamGB = 4, $vpsDiskGB = 50)
    {
        // Calculate based on VPS specs
        $maxConcurrentStreams = min($vpsCpuCores * 2, 8); // 2 streams per core, max 8
        $bufferSizeGB = min($vpsRamGB * 0.5, $vpsDiskGB * 0.1, 5); // Max 5GB buffer
        $chunkSizeMB = max(50, min(200, $vpsRamGB * 25)); // 50-200MB chunks
        
        return [
            'recommended_settings' => [
                'max_concurrent_streams' => $maxConcurrentStreams,
                'buffer_size_gb' => $bufferSizeGB,
                'chunk_size_mb' => $chunkSizeMB,
                'max_chunks' => intval(($bufferSizeGB * 1024) / $chunkSizeMB)
            ],
            'performance_estimate' => [
                'streams_per_2gb_buffer' => 'Unlimited',
                'buffer_fill_time_minutes' => round($bufferSizeGB * 2, 1),
                'disk_usage_per_stream_gb' => $bufferSizeGB,
                'cpu_usage_per_stream_percent' => round(100 / $maxConcurrentStreams, 1)
            ],
            'scaling_potential' => [
                'with_4gb_vps' => '8-12 concurrent streams',
                'with_8gb_vps' => '16-24 concurrent streams',
                'bottleneck' => 'CPU encoding, not bandwidth'
            ]
        ];
    }
} 