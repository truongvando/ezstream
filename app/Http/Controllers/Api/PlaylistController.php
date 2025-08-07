<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\PlaylistStreamingService;
use App\Services\PlaylistCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlaylistController extends Controller
{
    protected $playlistService;
    protected $commandService;

    public function __construct(PlaylistStreamingService $playlistService, PlaylistCommandService $commandService)
    {
        $this->playlistService = $playlistService;
        $this->commandService = $commandService;
    }

    /**
     * Validate files for playlist
     */
    public function validateFiles(Request $request)
    {
        try {
            $request->validate([
                'file_ids' => 'required|array',
                'file_ids.*' => 'integer|exists:user_files,id'
            ]);

            $user = Auth::user();
            $fileIds = $request->input('file_ids');

            $validation = $this->playlistService->validatePlaylistFiles($fileIds, $user->id);

            return response()->json([
                'status' => 'success',
                'valid_files' => count($validation['valid_files']),
                'total_files' => count($fileIds),
                'errors' => $validation['errors'],
                'files' => $validation['valid_files']->map(function($file) {
                    return [
                        'id' => $file->id,
                        'name' => $file->original_name,
                        'type' => $file->stream_video_id ? 'stream_library' : 'storage',
                        'status' => $file->stream_video_id ? 
                            ($file->stream_metadata['processing_status'] ?? 'unknown') : 'ready',
                        'hls_url' => $file->stream_video_id ? 
                            $file->stream_metadata['hls_url'] ?? null : null
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Playlist validation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed'
            ], 500);
        }
    }

    /**
     * Update playlist for stream
     */
    public function updatePlaylist(Request $request, StreamConfiguration $stream)
    {
        try {
            // Check ownership
            if ($stream->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'file_ids' => 'required|array',
                'file_ids.*' => 'integer|exists:user_files,id'
            ]);

            $fileIds = $request->input('file_ids');

            // Validate files first
            $validation = $this->playlistService->validatePlaylistFiles($fileIds, Auth::id());
            
            if (!empty($validation['errors'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Some files are not valid for playlist',
                    'errors' => $validation['errors']
                ], 400);
            }

            // Update playlist
            $success = $this->playlistService->updatePlaylist($stream, $fileIds);

            if ($success) {
                Log::info('Playlist updated successfully', [
                    'stream_id' => $stream->id,
                    'user_id' => Auth::id(),
                    'file_count' => count($fileIds)
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Playlist updated successfully',
                    'file_count' => count($fileIds),
                    'stream_status' => $stream->status
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update playlist'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Playlist update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Update failed'
            ], 500);
        }
    }

    /**
     * Get playlist status
     */
    public function getStatus(StreamConfiguration $stream)
    {
        try {
            // Check ownership
            if ($stream->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $status = $this->playlistService->getPlaylistStatus($stream);

            return response()->json([
                'status' => 'success',
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get playlist status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get status'
            ], 500);
        }
    }

    /**
     * Add videos to running stream playlist
     */
    public function addVideos(Request $request, $streamId)
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'integer|exists:user_files,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $stream = StreamConfiguration::findOrFail($streamId);

            if (!$this->canManageStream($stream)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $result = $this->commandService->addVideos($stream, $request->file_ids);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Videos added successfully',
                    'added_count' => count($request->file_ids)
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error("Failed to add videos to stream {$streamId}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to add videos'], 500);
        }
    }

    /**
     * Remove videos from running stream playlist
     */
    public function removeVideos(Request $request, $streamId)
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'integer|exists:user_files,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $stream = StreamConfiguration::findOrFail($streamId);

            if (!$this->canManageStream($stream)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $result = $this->commandService->deleteVideos($stream, $request->file_ids);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Videos removed successfully',
                    'removed_count' => count($request->file_ids)
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error("Failed to remove videos from stream {$streamId}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to remove videos'], 500);
        }
    }

    /**
     * Set loop mode for running stream
     */
    public function setLoopMode(Request $request, $streamId)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $stream = StreamConfiguration::findOrFail($streamId);

            if (!$this->canManageStream($stream)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $result = $this->commandService->setLoopMode($stream, $request->enabled);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loop mode updated',
                    'loop_enabled' => $request->enabled
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error("Failed to set loop mode for stream {$streamId}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to set loop mode'], 500);
        }
    }

    /**
     * Set playback order for running stream
     */
    public function setPlaybackOrder(Request $request, $streamId)
    {
        $validator = Validator::make($request->all(), [
            'order' => 'required|in:sequential,random'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            $stream = StreamConfiguration::findOrFail($streamId);

            if (!$this->canManageStream($stream)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $result = $this->commandService->setPlaybackOrder($stream, $request->order);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Playback order updated',
                    'playlist_order' => $request->order
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error("Failed to set playback order for stream {$streamId}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to set playback order'], 500);
        }
    }

    /**
     * Check if user can manage the stream
     */
    private function canManageStream(StreamConfiguration $stream): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Admin can manage all streams
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only manage their own streams
        return $stream->user_id === $user->id;
    }
}
