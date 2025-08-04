<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamConfiguration;
use App\Services\PlaylistStreamingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlaylistController extends Controller
{
    protected $playlistService;

    public function __construct(PlaylistStreamingService $playlistService)
    {
        $this->playlistService = $playlistService;
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
}
