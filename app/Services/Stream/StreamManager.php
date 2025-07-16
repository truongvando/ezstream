<?php

namespace App\Services\Stream;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\Vps\VpsConnection;
use Illuminate\Support\Facades\Log;

/**
 * Stream Manager Service
 * Chỉ chịu trách nhiệm: Quản lý lifecycle của streams
 */
class StreamManager
{
    private VpsConnection $vpsConnection;
    private StreamAllocation $streamAllocation;
    
    public function __construct(VpsConnection $vpsConnection, StreamAllocation $streamAllocation)
    {
        $this->vpsConnection = $vpsConnection;
        $this->streamAllocation = $streamAllocation;
    }
    
    /**
     * Start a stream
     */
    public function startStream(StreamConfiguration $stream): array
    {
        try {
            Log::info("Starting stream", ['stream_id' => $stream->id, 'title' => $stream->title]);
            
            // Check if stream is already running
            if ($stream->status === 'STREAMING') {
                return [
                    'success' => false,
                    'error' => 'Stream is already running'
                ];
            }
            
            // Find optimal VPS
            $vps = $this->streamAllocation->findOptimalVps($stream);
            if (!$vps) {
                return [
                    'success' => false,
                    'error' => 'No available VPS found'
                ];
            }
            
            // Update stream with VPS assignment - only update status if not already STARTING
            $updateData = [
                'vps_server_id' => $vps->id,
                'last_started_at' => now(),
                'error_message' => null
            ];
            
            // Chỉ cập nhật status nếu không phải đang STARTING
            if ($stream->status !== 'STARTING') {
                $updateData['status'] = 'STARTING';
            }
            
            $stream->update($updateData);
            
            // Prepare stream configuration
            $config = $this->prepareStreamConfig($stream);
            
            // Send start command to VPS
            $result = $this->sendStartCommand($vps, $config);
            
            if ($result['success']) {
                // Increment VPS stream count
                $vps->increment('current_streams');
                
                Log::info("Stream start command sent successfully", [
                    'stream_id' => $stream->id,
                    'vps_id' => $vps->id
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Stream start command sent to VPS',
                    'vps_id' => $vps->id
                ];
            } else {
                // Revert stream status on failure
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => $result['error']
                ]);
                
                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to start stream", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
            
            $stream->update([
                'status' => 'ERROR',
                'error_message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Stop a stream
     */
    public function stopStream(StreamConfiguration $stream): array
    {
        try {
            Log::info("Stopping stream", ['stream_id' => $stream->id]);
            
            if (!$stream->vpsServer) {
                return [
                    'success' => false,
                    'error' => 'No VPS assigned to stream'
                ];
            }
            
            // Send stop command to VPS
            $result = $this->sendStopCommand($stream->vpsServer, $stream->id);
            
            if ($result['success']) {
                // Decrement VPS stream count
                if ($stream->vpsServer->current_streams > 0) {
                    $stream->vpsServer->decrement('current_streams');
                }
                
                // Update stream status
                $stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                    'error_message' => null
                ]);
                
                Log::info("Stream stopped successfully", [
                    'stream_id' => $stream->id,
                    'vps_id' => $stream->vps_server_id
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Stream stopped successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to stop stream", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare stream configuration for VPS
     */
    private function prepareStreamConfig(StreamConfiguration $stream): array
    {
        $files = [];

        $videoSourcePath = $stream->video_source_path ?? [];
        foreach ($videoSourcePath as $fileInfo) {
            $userFile = \App\Models\UserFile::find($fileInfo['file_id']);
            if (!$userFile) {
                continue;
            }
            
            $downloadUrl = $this->getDownloadUrl($userFile);
            if ($downloadUrl) {
                $files[] = [
                    'file_id' => $userFile->id,
                    'filename' => $userFile->original_name,
                    'download_url' => $downloadUrl,
                    'size' => $userFile->size
                ];
            }
        }
        
        return [
            'stream_id' => $stream->id,
            'title' => $stream->title,
            'rtmp_url' => $stream->rtmp_url,
            'stream_key' => $stream->stream_key,
            'files' => $files,
            'loop' => $stream->loop ?? false,
            'playlist_order' => $stream->playlist_order ?? 'sequential',
            'user_id' => $stream->user_id
        ];
    }
    
    /**
     * Get download URL for file
     */
    private function getDownloadUrl(\App\Models\UserFile $userFile): ?string
    {
        try {
            if ($userFile->disk === 'bunny_cdn') {
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->getDirectDownloadLink($userFile->path);
                if ($result['success']) {
                    return $result['download_link'];
                }
            }
            
            // Fallback to secure download
            $downloadToken = \Illuminate\Support\Str::random(32);
            cache()->put("download_token_{$downloadToken}", $userFile->id, now()->addDays(7));
            
            return url("/api/secure-download/{$downloadToken}");
            
        } catch (\Exception $e) {
            Log::error("Failed to get download URL", [
                'file_id' => $userFile->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Send start command to VPS
     */
    private function sendStartCommand(VpsServer $vps, array $config): array
    {
        // Create config file
        $configJson = json_encode($config, JSON_PRETTY_PRINT);
        $configPath = "/tmp/stream_{$config['stream_id']}_config.json";
        
        // Upload config to VPS
        $tempFile = tempnam(sys_get_temp_dir(), 'stream_config_');
        file_put_contents($tempFile, $configJson);
        
        if (!$this->vpsConnection->uploadFile($vps, $tempFile, $configPath)) {
            unlink($tempFile);
            return ['success' => false, 'error' => 'Failed to upload config to VPS'];
        }
        unlink($tempFile);
        
        // Call API endpoint instead of running Python script directly
        $command = "curl -s -X POST http://localhost:9999/start_stream -H 'Content-Type: application/json' -d @{$configPath}";

        $result = $this->vpsConnection->executeCommand($vps, $command);
        
        // Cleanup
        $this->vpsConnection->executeCommand($vps, "rm -f {$configPath}");
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => "Command failed: " . ($result['error'] ?? 'Unknown error')
            ];
        }
        
        // Parse response
        $responseData = json_decode(trim($result['output']), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid response from VPS'
            ];
        }
        
        if (isset($responseData['error'])) {
            return [
                'success' => false,
                'error' => $responseData['error']
            ];
        }
        
        return ['success' => true, 'response' => $responseData];
    }
    
    /**
     * Send stop command to VPS
     */
    private function sendStopCommand(VpsServer $vps, int $streamId): array
    {
        $command = "cd /opt/multistream && python3 -c \"
import json
import sys
sys.path.append('/opt/multistream')
from manager import MultistreamManager

manager = MultistreamManager({$vps->id}, '" . config('app.url') . "', 'dummy-token')
result = manager.stop_stream({$streamId})
print(json.dumps(result))
\"";
        
        $result = $this->vpsConnection->executeCommand($vps, $command);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'error' => "Command failed: " . ($result['error'] ?? 'Unknown error')
            ];
        }
        
        $responseData = json_decode(trim($result['output']), true);
        if (json_last_error() !== JSON_ERROR_NONE || isset($responseData['error'])) {
            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Invalid response from VPS'
            ];
        }
        
        return ['success' => true, 'response' => $responseData];
    }
}
