<?php

namespace App\Services;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaylistStreamingService
{
    /**
     * Generate FFmpeg concat file for multiple videos
     */
    public function generateConcatFile(StreamConfiguration $stream): string
    {
        try {
            $videoFiles = $stream->videoFiles()->orderBy('playlist_order')->get();
            
            if ($videoFiles->isEmpty()) {
                throw new \Exception('No video files found for stream');
            }

            $concatContent = '';
            
            foreach ($videoFiles as $file) {
                if ($file->stream_video_id) {
                    // Stream Library file - use HLS URL
                    $url = $this->getStreamLibraryUrl($file);
                } else {
                    // Regular file - use CDN URL
                    $url = $file->public_url ?? $file->path;
                }
                
                if ($url) {
                    $concatContent .= "file '{$url}'\n";
                }
            }

            if (empty($concatContent)) {
                throw new \Exception('No valid video URLs found');
            }

            // Generate unique filename
            $filename = "playlist_{$stream->id}_" . time() . ".txt";
            $tempPath = storage_path("app/temp/{$filename}");
            
            // Ensure temp directory exists
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Write concat file
            file_put_contents($tempPath, $concatContent);

            Log::info('Generated concat file for playlist streaming', [
                'stream_id' => $stream->id,
                'file_count' => $videoFiles->count(),
                'concat_file' => $filename
            ]);

            return $tempPath;

        } catch (\Exception $e) {
            Log::error('Failed to generate concat file: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate SRS playlist configuration
     */
    public function generateSrsPlaylistConfig(StreamConfiguration $stream): array
    {
        try {
            $videoFiles = $stream->videoFiles()->orderBy('playlist_order')->get();
            
            $playlist = [];
            
            foreach ($videoFiles as $file) {
                if ($file->stream_video_id) {
                    // Stream Library file
                    $url = $this->getStreamLibraryUrl($file);
                    $type = 'hls';
                } else {
                    // Regular file
                    $url = $file->public_url ?? $file->path;
                    $type = 'file';
                }
                
                if ($url) {
                    $playlist[] = [
                        'id' => $file->id,
                        'name' => $file->original_name,
                        'url' => $url,
                        'type' => $type,
                        'duration' => $file->stream_metadata['duration'] ?? null,
                        'order' => $file->pivot->playlist_order ?? 0
                    ];
                }
            }

            return [
                'stream_id' => $stream->id,
                'playlist' => $playlist,
                'loop' => $stream->loop,
                'total_files' => count($playlist)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate SRS playlist config: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update playlist during streaming
     */
    public function updatePlaylist(StreamConfiguration $stream, array $newFileIds): bool
    {
        try {
            Log::info('Updating playlist for active stream', [
                'stream_id' => $stream->id,
                'new_files' => $newFileIds
            ]);

            // Update database relationships
            $stream->videoFiles()->detach();
            
            foreach ($newFileIds as $index => $fileId) {
                $stream->videoFiles()->attach($fileId, ['playlist_order' => $index]);
            }

            // If stream is currently active, update the running stream
            if ($stream->status === 'streaming') {
                return $this->updateActiveStream($stream);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update playlist: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update active stream with new playlist
     */
    private function updateActiveStream(StreamConfiguration $stream): bool
    {
        try {
            // Generate new playlist configuration
            $playlistConfig = $this->generateSrsPlaylistConfig($stream);
            
            // Send update command to agent via Redis
            $command = [
                'command' => 'UPDATE_PLAYLIST',
                'stream_id' => $stream->id,
                'playlist_config' => $playlistConfig,
                'timestamp' => now()->toISOString()
            ];

            // Dispatch to Redis for agent processing
            $redisKey = "stream_commands:{$stream->vps_server_id}";
            \Illuminate\Support\Facades\Redis::lpush($redisKey, json_encode($command));

            Log::info('Sent playlist update command to agent', [
                'stream_id' => $stream->id,
                'vps_id' => $stream->vps_server_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update active stream: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Stream Library URL for file
     */
    private function getStreamLibraryUrl(UserFile $file): ?string
    {
        if (empty($file->stream_video_id)) {
            return null;
        }

        // Return HLS playlist URL
        $cdnHostname = config('bunnycdn.stream_cdn_hostname');
        return "https://{$cdnHostname}/{$file->stream_video_id}/playlist.m3u8";
    }

    /**
     * Validate playlist files
     */
    public function validatePlaylistFiles(array $fileIds, int $userId): array
    {
        $validFiles = [];
        $errors = [];

        foreach ($fileIds as $fileId) {
            $file = UserFile::where('id', $fileId)
                           ->where('user_id', $userId)
                           ->first();

            if (!$file) {
                $errors[] = "File ID {$fileId} not found or not owned by user";
                continue;
            }

            // Check if file is ready for streaming
            if ($file->stream_video_id) {
                $processingStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
                if ($processingStatus !== 'completed') {
                    $errors[] = "File '{$file->original_name}' is still processing (status: {$processingStatus})";
                    continue;
                }
            }

            $validFiles[] = $file;
        }

        return [
            'valid_files' => $validFiles,
            'errors' => $errors
        ];
    }

    /**
     * Get playlist status for stream
     */
    public function getPlaylistStatus(StreamConfiguration $stream): array
    {
        $files = $stream->videoFiles()->orderBy('playlist_order')->get();
        
        $status = [
            'total_files' => $files->count(),
            'ready_files' => 0,
            'processing_files' => 0,
            'failed_files' => 0,
            'files' => []
        ];

        foreach ($files as $file) {
            $fileStatus = [
                'id' => $file->id,
                'name' => $file->original_name,
                'order' => $file->pivot->playlist_order,
                'type' => $file->stream_video_id ? 'stream_library' : 'storage',
                'status' => 'ready'
            ];

            if ($file->stream_video_id) {
                $processingStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
                $fileStatus['status'] = $processingStatus;
                
                switch ($processingStatus) {
                    case 'completed':
                        $status['ready_files']++;
                        break;
                    case 'processing':
                        $status['processing_files']++;
                        break;
                    case 'failed':
                        $status['failed_files']++;
                        break;
                }
            } else {
                $status['ready_files']++;
            }

            $status['files'][] = $fileStatus;
        }

        return $status;
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTempFiles(int $olderThanHours = 24): void
    {
        try {
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                return;
            }

            $cutoffTime = time() - ($olderThanHours * 3600);
            $files = glob($tempDir . '/playlist_*.txt');

            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    Log::debug('Cleaned up old playlist file', ['file' => basename($file)]);
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to cleanup temp files: ' . $e->getMessage());
        }
    }
}
