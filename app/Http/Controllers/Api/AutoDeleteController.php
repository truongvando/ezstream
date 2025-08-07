<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutoDeleteVideoService;
use App\Services\AutoDeleteMonitoringService;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AutoDeleteController extends Controller
{
    private AutoDeleteVideoService $autoDeleteService;
    private AutoDeleteMonitoringService $monitoringService;

    public function __construct(
        AutoDeleteVideoService $autoDeleteService,
        AutoDeleteMonitoringService $monitoringService
    ) {
        $this->autoDeleteService = $autoDeleteService;
        $this->monitoringService = $monitoringService;
    }

    /**
     * Get auto-delete dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        try {
            $data = $this->monitoringService->getDashboardData();
            return response()->json($data);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Dashboard failed: {$e->getMessage()}");
            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get health status
     */
    public function health(): JsonResponse
    {
        try {
            $health = $this->monitoringService->getHealthStatus();
            return response()->json($health);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Health check failed: {$e->getMessage()}");
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get videos scheduled for deletion
     */
    public function scheduled(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);
            $videos = $this->autoDeleteService->getVideosScheduledForDeletion($limit);

            return response()->json([
                'success' => true,
                'data' => $videos,
                'count' => count($videos)
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Get scheduled failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process scheduled deletions manually
     */
    public function processScheduled(): JsonResponse
    {
        try {
            Log::info("🗑️ [AutoDeleteAPI] Manual processing triggered by user");
            
            $result = $this->autoDeleteService->processScheduledDeletions();
            
            $this->monitoringService->logOperation('manual_process_scheduled', [
                'result' => $result,
                'triggered_by' => 'api'
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Process scheduled failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule video deletion for a specific stream
     */
    public function scheduleStream(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'stream_id' => 'required|integer|exists:stream_configurations,id',
                'delay_minutes' => 'integer|min:0|max:1440' // Max 24 hours
            ]);

            $stream = StreamConfiguration::findOrFail($request->stream_id);
            $delayMinutes = $request->get('delay_minutes', 5);

            $result = $this->autoDeleteService->scheduleVideoDeletion($stream, $delayMinutes);
            
            $this->monitoringService->logOperation('manual_schedule_stream', [
                'stream_id' => $stream->id,
                'delay_minutes' => $delayMinutes,
                'result' => $result,
                'triggered_by' => 'api'
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Schedule stream failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel scheduled deletion for a file
     */
    public function cancelScheduled(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_id' => 'required|integer|exists:user_files,id'
            ]);

            $file = UserFile::findOrFail($request->file_id);
            $result = $this->autoDeleteService->cancelScheduledDeletion($file);

            $this->monitoringService->logOperation('cancel_scheduled_deletion', [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'success' => $result,
                'triggered_by' => 'api'
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Deletion cancelled successfully' : 'Failed to cancel deletion'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Cancel scheduled failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force delete a video immediately
     */
    public function forceDelete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file_id' => 'required|integer|exists:user_files,id'
            ]);

            $file = UserFile::findOrFail($request->file_id);
            
            // Check if file is still in use
            $activeStreams = StreamConfiguration::whereJsonContains('video_source_path', [['file_id' => $file->id]])
                ->whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
                ->count();

            if ($activeStreams > 0) {
                return response()->json([
                    'success' => false,
                    'error' => "File is still in use by {$activeStreams} active stream(s)"
                ], 400);
            }

            $result = $this->autoDeleteService->deleteVideoFromAllSources($file);
            
            $this->monitoringService->logOperation('force_delete', [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'result' => $result,
                'triggered_by' => 'api'
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Force delete failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get auto-delete statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->monitoringService->getStatistics();
            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Statistics failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear monitoring cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->monitoringService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Clear cache failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on scheduled deletions
     */
    public function bulkOperation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'operation' => 'required|in:cancel,force_delete,reschedule',
                'file_ids' => 'required|array|min:1',
                'file_ids.*' => 'integer|exists:user_files,id',
                'delay_minutes' => 'integer|min:0|max:1440' // For reschedule operation
            ]);

            $operation = $request->operation;
            $fileIds = $request->file_ids;
            $delayMinutes = $request->get('delay_minutes', 5);

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($fileIds as $fileId) {
                try {
                    $file = UserFile::findOrFail($fileId);
                    
                    switch ($operation) {
                        case 'cancel':
                            $success = $this->autoDeleteService->cancelScheduledDeletion($file);
                            break;
                            
                        case 'force_delete':
                            $result = $this->autoDeleteService->deleteVideoFromAllSources($file);
                            $success = $result['success'];
                            break;
                            
                        case 'reschedule':
                            $file->update(['scheduled_deletion_at' => now()->addMinutes($delayMinutes)]);
                            $success = true;
                            break;
                            
                        default:
                            $success = false;
                    }
                    
                    $results[$fileId] = ['success' => $success];
                    if ($success) $successCount++;
                    else $errorCount++;
                    
                } catch (\Exception $e) {
                    $results[$fileId] = ['success' => false, 'error' => $e->getMessage()];
                    $errorCount++;
                }
            }

            $this->monitoringService->logOperation('bulk_operation', [
                'operation' => $operation,
                'file_count' => count($fileIds),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'triggered_by' => 'api'
            ]);

            return response()->json([
                'success' => true,
                'operation' => $operation,
                'total_files' => count($fileIds),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteAPI] Bulk operation failed: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
