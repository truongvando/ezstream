<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\StreamConfiguration;
use App\Services\StreamProgressService;
use App\Models\SystemEvent;

class AgentReportController extends Controller
{
    /**
     * Handle status reports from VPS agents.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleReport(Request $request)
    {
        // Ghi lại sự kiện nhận được webhook
        SystemEvent::create([
            'level' => 'info',
            'type' => 'WEBHOOK_RECEIVED',
            'message' => 'Received a report from agent on IP: ' . $request->ip(),
            'context' => $request->all()
        ]);

        // 2. Validate the incoming data
        $validator = Validator::make($request->all(), [
            'stream_id' => 'required|integer',
            'vps_id' => 'required|integer',
            'status' => 'required|string|in:STARTING,STREAMING,STOPPED,COMPLETED,ERROR,PROGRESS',
            'message' => 'nullable|string|max:1000',
            'timestamp' => 'required|integer',
            'extra_data' => 'nullable|array' // For progress data
        ]);

        if ($validator->fails()) {
            SystemEvent::create([
                'level' => 'error',
                'type' => 'WEBHOOK_VALIDATION_FAILED',
                'message' => 'Webhook validation failed.',
                'context' => ['errors' => $validator->errors()->all(), 'payload' => $request->all()]
            ]);
            Log::warning('[AgentReport] Validation failed', [
                'errors' => $validator->errors()->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            // 3. Find the stream and update its status
            $stream = StreamConfiguration::find($validatedData['stream_id']);

            if (!$stream) {
                SystemEvent::create([
                    'level' => 'warning',
                    'type' => 'WEBHOOK_STREAM_NOT_FOUND',
                    'message' => "Stream #{$validatedData['stream_id']} not found, but acknowledged the report.",
                    'context' => $validatedData
                ]);
                Log::warning('[AgentReport] Stream not found', ['stream_id' => $validatedData['stream_id']]);
                // Return 200 OK even if stream not found, so agent doesn't retry unnecessarily.
                // The sync mechanism will handle cleanup.
                return response()->json([
                    'status' => 'success',
                    'message' => 'Report received, but stream not found. Acknowledged.'
                ]);
            }

            // --- TÁCH LOGIC XỬ LÝ ---

            // A. Nếu là báo cáo STARTING
            if ($validatedData['status'] === 'STARTING') {
                $stream->status = 'STARTING';
                $stream->vps_server_id = $validatedData['vps_id'];
                $stream->last_status_update = now();
                $stream->save();

                Log::info("[AgentReport] Stream #{$stream->id} is STARTING on VPS #{$validatedData['vps_id']}");
                
                return response()->json([
                    'status' => 'success',
                    'message' => "Stream #{$stream->id} acknowledged as STARTING."
                ]);
            }

            // B. Nếu đây là báo cáo PROGRESS
            if ($validatedData['status'] === 'PROGRESS') {
                $progressData = $validatedData['extra_data']['progress_data'] ?? null;

                if ($progressData) {
                    StreamProgressService::createStageProgress(
                        $stream->id,
                        $progressData['stage'] ?? 'unknown',
                        $validatedData['message'] ?? 'Updating progress...',
                        $progressData['progress_percentage'] ?? 0,
                        $progressData['details'] ?? []
                    );
                    Log::info("[AgentReport] Recorded progress for stream #{$stream->id}", ['stage' => $progressData['stage']]);
                } else {
                    Log::warning("[AgentReport] Received PROGRESS status without progress_data", ['stream_id' => $stream->id]);
                }
                
                // Trả về thành công ngay sau khi ghi nhận progress
                return response()->json([
                    'status' => 'success',
                    'message' => "Progress for stream #{$stream->id} acknowledged."
                ]);
            }

            // C. Nếu đây là báo cáo thay đổi TRẠNG THÁI (status) khác
            $oldStatus = $stream->status;
            $newStatus = $validatedData['status'];

            // Apply business logic: STOPPED should become INACTIVE for UI consistency
            if ($newStatus === 'STOPPED') {
                $newStatus = 'INACTIVE';

                // Clean up VPS assignment when stream stops
                $originalVpsId = $stream->vps_server_id;
                if ($originalVpsId) {
                    $stream->vps_server_id = null;
                    $stream->process_id = null;

                    // Decrement VPS stream count
                    \App\Models\VpsServer::find($originalVpsId)?->decrement('current_streams');
                }

                $stream->last_stopped_at = now();
            } else {
                $stream->vps_server_id = $validatedData['vps_id']; // Ensure vps_id is correct
            }

            $stream->status = $newStatus;

            if (isset($validatedData['message'])) {
                 $stream->error_message = $validatedData['message'];
            }

            $stream->last_status_update = now();
            $stream->save();

            SystemEvent::create([
                'level' => 'info',
                'type' => 'WEBHOOK_STATUS_UPDATED',
                'message' => "Stream #{$stream->id} status updated from '{$oldStatus}' to '{$stream->status}'.",
                'context' => $validatedData
            ]);

            // 5. Return a success response
            return response()->json([
                'status' => 'success',
                'message' => "Stream #{$stream->id} status updated to {$stream->status}."
            ]);

        } catch (\Exception $e) {
            SystemEvent::create([
                'level' => 'error',
                'type' => 'WEBHOOK_PROCESSING_ERROR',
                'message' => 'Failed to process agent report due to an internal error.',
                'context' => [
                    'error_message' => $e->getMessage(),
                    'payload' => $request->all()
                ]
            ]);
            Log::error('[AgentReport] Failed to process report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An internal server error occurred.'
            ], 500);
        }
    }
} 