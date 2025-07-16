<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamConfiguration;
use App\Services\Stream\StreamManager;
use App\Services\Stream\StreamAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Stream API Controller
 * Chỉ chịu trách nhiệm: API endpoints cho stream management
 */
class StreamController extends Controller
{
    private StreamManager $streamManager;
    private StreamAllocation $streamAllocation;
    
    public function __construct(StreamManager $streamManager, StreamAllocation $streamAllocation)
    {
        $this->streamManager = $streamManager;
        $this->streamAllocation = $streamAllocation;
    }
    
    /**
     * Start a stream
     * POST /api/stream/{id}/start
     */
    public function start(StreamConfiguration $stream): JsonResponse
    {
        try {
            // Check permissions
            if (!$this->canManageStream($stream)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }
            
            $result = $this->streamManager->startStream($stream);
            
            return response()->json($result, $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            Log::error("Stream start API error", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Stop a stream
     * POST /api/stream/{id}/stop
     */
    public function stop(StreamConfiguration $stream): JsonResponse
    {
        try {
            // Check permissions
            if (!$this->canManageStream($stream)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }
            
            $result = $this->streamManager->stopStream($stream);
            
            return response()->json($result, $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            Log::error("Stream stop API error", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Get stream status
     * GET /api/stream/{id}/status
     */
    public function status(StreamConfiguration $stream): JsonResponse
    {
        try {
            // Check permissions
            if (!$this->canViewStream($stream)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ], 403);
            }
            
            $status = [
                'stream_id' => $stream->id,
                'title' => $stream->title,
                'status' => $stream->status,
                'vps_id' => $stream->vps_server_id,
                'vps_name' => $stream->vpsServer?->name,
                'last_started_at' => $stream->last_started_at,
                'last_stopped_at' => $stream->last_stopped_at,
                'error_message' => $stream->error_message,
                'created_at' => $stream->created_at,
                'updated_at' => $stream->updated_at
            ];
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error("Stream status API error", [
                'stream_id' => $stream->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Get stream allocation info
     * GET /api/stream/allocation-stats
     */
    public function allocationStats(): JsonResponse
    {
        try {
            $stats = $this->streamAllocation->getAllocationStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error("Stream allocation stats API error", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Check if user can manage stream
     */
    private function canManageStream(StreamConfiguration $stream): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Admin can manage all streams
        if ($user->role === 'admin') {
            return true;
        }
        
        // User can only manage their own streams
        return $stream->user_id === $user->id;
    }
    
    /**
     * Check if user can view stream
     */
    private function canViewStream(StreamConfiguration $stream): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Admin can view all streams
        if ($user->role === 'admin') {
            return true;
        }
        
        // User can only view their own streams
        return $stream->user_id === $user->id;
    }
}
