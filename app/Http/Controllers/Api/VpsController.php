<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VpsController extends Controller
{
    /**
     * Receive status updates from VPS multistream manager
     */
    public function updateStatus(Request $request, $vpsId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vps_id' => 'required|integer',
            'active_streams' => 'required|integer|min:0',
            'max_streams' => 'required|integer|min:1',
            'available_capacity' => 'required|integer|min:0',
            'streams' => 'array',
            'system' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        try {
            $vps = VpsServer::findOrFail($vpsId);
            
            // Update VPS status
            $vps->update([
                'current_streams' => $request->active_streams,
                'max_concurrent_streams' => $request->max_streams,
                'last_seen_at' => now(),
                'status' => 'ACTIVE'
            ]);

            // Update individual stream statuses if provided
            if ($request->has('streams')) {
                foreach ($request->streams as $streamId => $streamStatus) {
                    $stream = StreamConfiguration::find($streamId);
                    if ($stream && $stream->vps_server_id == $vpsId) {
                        $this->updateStreamFromVpsStatus($stream, $streamStatus);
                    }
                }
            }

            // Log system metrics if provided
            if ($request->has('system')) {
                Log::info("📊 [VPS #{$vpsId}] System metrics", [
                    'cpu_percent' => $request->system['cpu_percent'] ?? null,
                    'memory_percent' => $request->system['memory_percent'] ?? null,
                    'disk_percent' => $request->system['disk_percent'] ?? null,
                    'active_streams' => $request->active_streams
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Status updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Error updating VPS status", [
                'vps_id' => $vpsId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle provision complete notification from VPS
     */
    public function provisionComplete(Request $request, $vpsId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'capabilities' => 'array',
            'specs' => 'array',
            'services' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        try {
            $vps = VpsServer::findOrFail($vpsId);
            
            $updateData = [
                'status' => $request->status === 'ready' ? 'ACTIVE' : 'FAILED',
                'last_provisioned_at' => now(),
                'capabilities' => json_encode($request->capabilities ?? []),
            ];

            // Update specs if provided
            if ($request->has('specs')) {
                $specs = $request->specs;
                if (isset($specs['cpu_cores'])) {
                    $updateData['max_concurrent_streams'] = $this->calculateMaxStreams($specs);
                }
            }

            $vps->update($updateData);

            Log::info("✅ [VPS #{$vpsId}] Provision complete", [
                'status' => $request->status,
                'capabilities' => $request->capabilities,
                'specs' => $request->specs ?? null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Provision status updated'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Error updating provision status", [
                'vps_id' => $vpsId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get pending streams for a VPS (called by VPS manager)
     */
    public function getPendingStreams(Request $request, $vpsId): JsonResponse
    {
        try {
            $vps = VpsServer::findOrFail($vpsId);
            
            // Get streams assigned to this VPS that are in STARTING status
            $pendingStreams = StreamConfiguration::where('vps_server_id', $vpsId)
                ->where('status', 'STARTING')
                ->get()
                ->map(function ($stream) {
                    return [
                        'stream_id' => $stream->id,
                        'title' => $stream->title,
                        'rtmp_url' => $stream->rtmp_url,
                        'stream_key' => $stream->stream_key,
                        'files' => $stream->video_source_path,
                        'loop' => $stream->loop ?? false,
                        'playlist_order' => $stream->playlist_order ?? 'sequential',
                        'user_id' => $stream->user_id,
                    ];
                });

            return response()->json([
                'streams' => $pendingStreams
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Error getting pending streams", [
                'vps_id' => $vpsId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Receive stream webhook from VPS
     */
    public function streamWebhook(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stream_id' => 'required|integer',
            'status' => 'required|string',
            'message' => 'string',
            'vps_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        try {
            $stream = StreamConfiguration::findOrFail($request->stream_id);
            
            // Verify VPS ownership
            if ($stream->vps_server_id != $request->vps_id) {
                return response()->json(['error' => 'VPS mismatch'], 403);
            }

            // Update stream status based on webhook
            $statusMap = [
                'STREAMING' => 'STREAMING',
                'COMPLETED' => 'INACTIVE',
                'ERROR' => 'ERROR',
                'STOPPED' => 'INACTIVE'
            ];

            $newStatus = $statusMap[$request->status] ?? $request->status;
            
            $updateData = ['status' => $newStatus];
            
            if ($request->has('message')) {
                if ($request->status === 'ERROR') {
                    $updateData['error_message'] = $request->message;
                } else {
                    $updateData['error_message'] = null;
                }
            }

            if (in_array($newStatus, ['INACTIVE', 'ERROR'])) {
                $updateData['last_stopped_at'] = now();
                
                // Decrement VPS stream count
                $vps = $stream->vpsServer;
                if ($vps && $vps->current_streams > 0) {
                    $vps->decrement('current_streams');
                }
            }

            $stream->update($updateData);

            Log::info("📡 [Stream #{$request->stream_id}] Webhook received", [
                'status' => $request->status,
                'message' => $request->message ?? null,
                'vps_id' => $request->vps_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Error processing stream webhook", [
                'stream_id' => $request->stream_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update stream status from VPS status data
     */
    private function updateStreamFromVpsStatus(StreamConfiguration $stream, array $statusData): void
    {
        $vpsStatus = $statusData['status'] ?? 'unknown';
        
        $statusMap = [
            'running' => 'STREAMING',
            'stopped' => 'INACTIVE',
            'error' => 'ERROR',
            'not_started' => 'STARTING'
        ];

        $newStatus = $statusMap[$vpsStatus] ?? $stream->status;
        
        if ($newStatus !== $stream->status) {
            $stream->update(['status' => $newStatus]);
            
            Log::info("🔄 [Stream #{$stream->id}] Status updated from VPS", [
                'old_status' => $stream->status,
                'new_status' => $newStatus,
                'vps_status' => $vpsStatus
            ]);
        }
    }

    /**
     * Calculate max concurrent streams based on VPS specs
     */
    private function calculateMaxStreams(array $specs): int
    {
        $cpuCores = $specs['cpu_cores'] ?? 1;
        $ramGB = $specs['ram_gb'] ?? 1;
        
        // Conservative calculation
        $maxByCpu = max(1, intval($cpuCores * 0.8 / 0.15)); // 15% CPU per stream
        $maxByRam = max(1, intval($ramGB * 0.8 / 0.2)); // 200MB RAM per stream
        
        return min($maxByCpu, $maxByRam, 10); // Hard limit of 10
    }
}
