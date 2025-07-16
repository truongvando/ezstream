<?php

namespace App\Http\Controllers;

use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StreamProgressController extends Controller
{
    /**
     * Get stream progress
     * GET /api/stream/{streamId}/progress
     */
    public function getProgress($streamId): JsonResponse
    {
        try {
            $stream = StreamConfiguration::find($streamId);
            
            if (!$stream) {
                return response()->json([
                    'success' => false,
                    'error' => 'Stream not found'
                ], 404);
            }
            
            // Progress is public information for UI updates
            // No authentication required for reading progress

            // Get progress from Redis cache
            $progress = StreamProgressService::getProgress($streamId);

            if (!$progress) {
                // Return default progress based on stream status
                $progress = StreamProgressService::getDefaultProgress($stream->status, $streamId);
            }

            // Add stream status to response
            $progress['stream_status'] = $stream->status;

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
            
        } catch (\Exception $e) {
            Log::error("Stream progress API error", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Clear stream progress
     * DELETE /api/stream/{streamId}/progress
     */
    public function clearProgress($streamId): JsonResponse
    {
        try {
            $stream = StreamConfiguration::find($streamId);
            
            if (!$stream) {
                return response()->json([
                    'success' => false,
                    'error' => 'Stream not found'
                ], 404);
            }
            
            // Check permissions
            if (!$this->canManageStream($stream)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }
            
            // Clear progress from Redis cache
            StreamProgressService::clearProgress($streamId);
            
            return response()->json([
                'success' => true,
                'message' => 'Progress cleared'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Clear stream progress API error", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    

    
    /**
     * Check if user can view stream
     */
    private function canViewStream(StreamConfiguration $stream): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Admin can view all streams
        if ($user->isAdmin()) {
            return true;
        }

        // User can view their own streams
        return $stream->user_id === $user->id;
    }

    /**
     * Check if user can manage stream
     */
    private function canManageStream(StreamConfiguration $stream): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Admin can manage all streams
        if ($user->isAdmin()) {
            return true;
        }

        // User can manage their own streams
        return $stream->user_id === $user->id;
    }
}
