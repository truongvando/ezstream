<?php

namespace App\Services;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service để handle playlist management commands qua Redis
 */
class PlaylistCommandService
{
    /**
     * Update playlist for a running stream
     */
    public function updatePlaylist(StreamConfiguration $stream, array $fileIds): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            // Validate files exist and belong to user
            $files = UserFile::whereIn('id', $fileIds)
                ->where('user_id', $stream->user_id)
                ->where('status', 'COMPLETED')
                ->get();

            if ($files->count() !== count($fileIds)) {
                return ['success' => false, 'error' => 'Some files not found or not accessible'];
            }

            // Prepare file list for agent
            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'file_id' => $file->id,
                    'filename' => $file->original_name,
                    'path' => $file->path,
                    'size' => $file->size,
                    'disk' => $file->disk,
                    'stream_video_id' => $file->stream_video_id,
                    'public_url' => $file->public_url
                ];
            }

            // Send UPDATE_PLAYLIST command
            $command = [
                'command' => 'UPDATE_PLAYLIST',
                'stream_id' => $stream->id,
                'files' => $fileList,
                'playlist_order' => $stream->playlist_order,
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            if ($result['success']) {
                // Update database
                $stream->update([
                    'video_source_path' => array_map(fn($id) => ['file_id' => $id], $fileIds)
                ]);

                Log::info("✅ [PlaylistCommand] Updated playlist for stream #{$stream->id}", [
                    'file_count' => count($fileIds),
                    'vps_id' => $stream->vps_server_id
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to update playlist for stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set loop mode for a running stream
     */
    public function setLoopMode(StreamConfiguration $stream, bool $enabled): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            $command = [
                'command' => 'SET_LOOP_MODE',
                'stream_id' => $stream->id,
                'enabled' => $enabled,
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            if ($result['success']) {
                // Update database
                $stream->update(['loop' => $enabled]);

                Log::info("✅ [PlaylistCommand] Set loop mode for stream #{$stream->id}", [
                    'enabled' => $enabled,
                    'vps_id' => $stream->vps_server_id
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to set loop mode for stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set playback order for a running stream
     */
    public function setPlaybackOrder(StreamConfiguration $stream, string $order): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            if (!in_array($order, ['sequential', 'random'])) {
                return ['success' => false, 'error' => 'Invalid playback order. Must be sequential or random'];
            }

            $command = [
                'command' => 'SET_PLAYBACK_ORDER',
                'stream_id' => $stream->id,
                'order' => $order,
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            if ($result['success']) {
                // Update database
                $stream->update(['playlist_order' => $order]);

                Log::info("✅ [PlaylistCommand] Set playback order for stream #{$stream->id}", [
                    'order' => $order,
                    'vps_id' => $stream->vps_server_id
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to set playback order for stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete specific videos from playlist
     */
    public function deleteVideos(StreamConfiguration $stream, array $fileIds): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            // Get current playlist
            $currentFiles = collect($stream->video_source_path)->pluck('file_id')->toArray();
            $remainingFiles = array_diff($currentFiles, $fileIds);

            if (empty($remainingFiles)) {
                return ['success' => false, 'error' => 'Cannot delete all videos from playlist'];
            }

            $command = [
                'command' => 'DELETE_VIDEOS',
                'stream_id' => $stream->id,
                'file_ids_to_delete' => $fileIds,
                'remaining_file_ids' => array_values($remainingFiles),
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            if ($result['success']) {
                // Update database
                $newVideoSourcePath = array_map(fn($id) => ['file_id' => $id], $remainingFiles);
                $stream->update(['video_source_path' => $newVideoSourcePath]);

                Log::info("✅ [PlaylistCommand] Deleted videos from stream #{$stream->id}", [
                    'deleted_count' => count($fileIds),
                    'remaining_count' => count($remainingFiles),
                    'vps_id' => $stream->vps_server_id
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to delete videos from stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Add videos to existing playlist
     */
    public function addVideos(StreamConfiguration $stream, array $fileIds): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            // Validate files
            $files = UserFile::whereIn('id', $fileIds)
                ->where('user_id', $stream->user_id)
                ->where('status', 'COMPLETED')
                ->get();

            if ($files->count() !== count($fileIds)) {
                return ['success' => false, 'error' => 'Some files not found or not accessible'];
            }

            // Get current playlist and add new files
            $currentFiles = collect($stream->video_source_path)->pluck('file_id')->toArray();
            $allFiles = array_unique(array_merge($currentFiles, $fileIds));

            // Prepare new file list for agent
            $newFileList = [];
            foreach ($files as $file) {
                $newFileList[] = [
                    'file_id' => $file->id,
                    'filename' => $file->original_name,
                    'path' => $file->path,
                    'size' => $file->size,
                    'disk' => $file->disk,
                    'stream_video_id' => $file->stream_video_id,
                    'public_url' => $file->public_url
                ];
            }

            $command = [
                'command' => 'ADD_VIDEOS',
                'stream_id' => $stream->id,
                'new_files' => $newFileList,
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            if ($result['success']) {
                // Update database
                $newVideoSourcePath = array_map(fn($id) => ['file_id' => $id], $allFiles);
                $stream->update(['video_source_path' => $newVideoSourcePath]);

                Log::info("✅ [PlaylistCommand] Added videos to stream #{$stream->id}", [
                    'added_count' => count($fileIds),
                    'total_count' => count($allFiles),
                    'vps_id' => $stream->vps_server_id
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to add videos to stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send command to VPS agent via Redis
     */
    private function sendCommand(int $vpsId, array $command): array
    {
        try {
            $channel = "vps-commands:{$vpsId}";
            $redis = Redis::connection();
            $publishResult = $redis->publish($channel, json_encode($command));

            if ($publishResult > 0) {
                return ['success' => true, 'subscribers' => $publishResult];
            } else {
                return ['success' => false, 'error' => 'No agent listening'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current playlist status from agent
     */
    public function getPlaylistStatus(StreamConfiguration $stream): array
    {
        try {
            if ($stream->status !== 'STREAMING') {
                return ['success' => false, 'error' => 'Stream is not currently running'];
            }

            $command = [
                'command' => 'GET_PLAYLIST_STATUS',
                'stream_id' => $stream->id,
                'timestamp' => now()->toISOString()
            ];

            $result = $this->sendCommand($stream->vps_server_id, $command);

            return $result;

        } catch (Exception $e) {
            Log::error("❌ [PlaylistCommand] Failed to get playlist status for stream #{$stream->id}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
