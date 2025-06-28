<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StreamWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Validate required fields
            $streamId = $request->input('stream_id');
            $status = $request->input('status');
            $message = $request->input('message');
            $webhookSecret = $request->header('X-Webhook-Secret');

            if (!$streamId || !$status || !$webhookSecret) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            // Find stream
            $stream = StreamConfiguration::find($streamId);
            if (!$stream) {
                Log::warning("Webhook for unknown stream", ['stream_id' => $streamId]);
                return response()->json(['error' => 'Stream not found'], 404);
            }

            // Validate webhook secret (check recent secrets to handle restarts)
            $validSecret = false;
            $currentTime = time();
            
            // Check secrets from last 2 hours (in case of restarts)
            for ($i = 0; $i < 7200; $i += 60) { // Check every minute for last 2 hours
                $testTime = $currentTime - $i;
                $secretKey = "webhook_secret_{$streamId}_{$testTime}";
                $expectedSecret = cache($secretKey);
                
                if ($expectedSecret && $webhookSecret === $expectedSecret) {
                    $validSecret = true;
                    break;
                }
            }
            
            if (!$validSecret) {
                Log::warning("Invalid webhook secret", [
                    'stream_id' => $streamId,
                    'provided_secret' => $webhookSecret
                ]);
                return response()->json(['error' => 'Invalid webhook secret'], 403);
            }

            Log::info("Stream webhook received", [
                'stream_id' => $streamId,
                'status' => $status,
                'message' => $message
            ]);

            // Update stream status based on webhook
            $this->updateStreamStatus($stream, $status, $message, $request);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error("Webhook processing error", [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    protected function updateStreamStatus(StreamConfiguration $stream, string $status, string $message, Request $request)
    {
        $updateData = [
            'output_log' => $message,
            'last_status_update' => now(),
        ];

        switch ($status) {
            case 'DOWNLOADING':
                $updateData['status'] = 'STARTING';
                break;

            case 'STREAMING':
                $updateData['status'] = 'STREAMING';
                $newPid = $request->input('new_pid');
                if ($newPid) {
                    $updateData['ffmpeg_pid'] = $newPid;
                }
                break;

            case 'RECOVERING':
                $updateData['status'] = 'STREAMING';
                $updateData['output_log'] = 'Auto-recovery: ' . $message;
                break;

            case 'HEARTBEAT':
                // Just update last status time, don't change status
                $updateData = [
                    'last_status_update' => now(),
                    'output_log' => $message
                ];
                break;

            case 'COMPLETED':
                $updateData['status'] = 'COMPLETED';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                break;

            case 'STOPPED':
                $updateData['status'] = 'STOPPED';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                break;

            case 'ERROR':
                $updateData['status'] = 'ERROR';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                break;

            default:
                Log::warning("Unknown webhook status", ['status' => $status]);
                return;
        }

        $stream->update($updateData);

        Log::info("Stream status updated", [
            'stream_id' => $stream->id,
            'old_status' => $stream->getOriginal('status'),
            'new_status' => $updateData['status'],
            'message' => $message
        ]);
    }
}
