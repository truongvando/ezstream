<?php

namespace App\Http\Controllers;

use App\Models\StreamConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookTestController extends Controller
{
    /**
     * Simulate webhook from VPS for testing
     */
    public function simulateWebhook(Request $request)
    {
        $streamId = $request->input('stream_id');
        $status = $request->input('status', 'STREAMING');
        $message = $request->input('message', 'Simulated webhook for testing');
        
        if (!$streamId) {
            return response()->json(['error' => 'stream_id required'], 400);
        }
        
        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }
        
        // Generate webhook secret (simulate VPS)
        $timestamp = time();
        $secret = hash('sha256', "webhook_secret_{$streamId}_{$timestamp}_" . config('app.key'));
        cache()->put("webhook_secret_{$streamId}_{$timestamp}", $secret, 7200);
        
        // Simulate webhook call to our own API
        $webhookData = [
            'stream_id' => $streamId,
            'status' => $status,
            'message' => $message,
            'timestamp' => $timestamp,
            'new_pid' => $status === 'STREAMING' ? rand(1000, 9999) : null,
        ];
        
        try {
            $response = Http::withHeaders([
                'X-Webhook-Secret' => $secret,
                'Content-Type' => 'application/json',
            ])->post(url('/api/stream-webhook'), $webhookData);
            
            Log::info("Simulated webhook sent", [
                'stream_id' => $streamId,
                'status' => $status,
                'response_status' => $response->status()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook simulated successfully',
                'webhook_data' => $webhookData,
                'response_status' => $response->status(),
                'response_body' => $response->json()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to simulate webhook',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test webhook interface for manual testing
     */
    public function testInterface()
    {
        $streams = StreamConfiguration::orderBy('created_at', 'desc')->limit(10)->get();
        
        return view('webhook-test', compact('streams'));
    }
    
    /**
     * Quick webhook test for specific stream and status
     */
    public function quickTest($streamId, $status = 'STREAMING')
    {
        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            return response()->json(['error' => 'Stream not found'], 404);
        }
        
        // Auto-generate appropriate message
        $messages = [
            'DOWNLOADING' => 'Files downloaded successfully, starting stream...',
            'STREAMING' => 'Stream is now live and broadcasting',
            'RECOVERING' => 'Connection lost, switching to backup RTMP...',
            'STOPPED' => 'Stream stopped by user request',
            'COMPLETED' => 'Stream completed successfully',
            'ERROR' => 'Stream failed due to network error',
            'HEARTBEAT' => 'Stream is healthy and running'
        ];
        
        $message = $messages[$status] ?? "Stream status: {$status}";
        
        return $this->simulateWebhook(request()->merge([
            'stream_id' => $streamId,
            'status' => $status,
            'message' => $message
        ]));
    }
} 