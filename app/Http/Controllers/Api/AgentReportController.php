<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\StreamConfiguration;

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
        // 1. Log the raw request for easier debugging
        Log::info('[AgentReport] Received report', [
            'ip' => $request->ip(),
            'payload' => $request->all()
        ]);

        // 2. Validate the incoming data
        $validator = Validator::make($request->all(), [
            'stream_id' => 'required|integer',
            'vps_id' => 'required|integer',
            'status' => 'required|string|in:STARTED,STREAMING,STOPPED,COMPLETED,ERROR',
            'message' => 'nullable|string|max:1000',
            'timestamp' => 'required|integer',
        ]);

        if ($validator->fails()) {
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
                Log::warning('[AgentReport] Stream not found', ['stream_id' => $validatedData['stream_id']]);
                // Return 200 OK even if stream not found, so agent doesn't retry unnecessarily.
                // The sync mechanism will handle cleanup.
                return response()->json([
                    'status' => 'success',
                    'message' => 'Report received, but stream not found. Acknowledged.'
                ]);
            }

            // 4. Update the stream record in the database
            $stream->status = $validatedData['status'];
            $stream->vps_server_id = $validatedData['vps_id']; // Ensure vps_id is correct
            
            if (isset($validatedData['message'])) {
                 $stream->error_message = $validatedData['message'];
            }

            $stream->last_status_update = now();
            $stream->save();

            Log::info("[AgentReport] Successfully updated stream #{$stream->id} to status {$stream->status}");

            // 5. Return a success response
            return response()->json([
                'status' => 'success',
                'message' => "Stream #{$stream->id} status updated to {$stream->status}."
            ]);

        } catch (\Exception $e) {
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