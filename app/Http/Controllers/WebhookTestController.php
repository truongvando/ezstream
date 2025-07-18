<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Test Controller - For testing webhook functionality
 */
class WebhookTestController extends Controller
{
    /**
     * Show webhook test interface
     */
    public function testInterface()
    {
        return response()->json([
            'message' => 'Webhook test interface',
            'endpoints' => [
                'simulate' => '/webhook-test/simulate',
                'quick' => '/webhook-test/quick/{streamId}/{status}'
            ]
        ]);
    }

    /**
     * Simulate webhook call
     */
    public function simulateWebhook(Request $request)
    {
        Log::info('Webhook simulation called', $request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Webhook simulation completed',
            'data' => $request->all()
        ]);
    }

    /**
     * Quick webhook test
     */
    public function quickTest($streamId, $status)
    {
        Log::info("Quick webhook test: Stream {$streamId} -> {$status}");
        
        return response()->json([
            'success' => true,
            'stream_id' => $streamId,
            'status' => $status,
            'message' => 'Quick test completed'
        ]);
    }
}
