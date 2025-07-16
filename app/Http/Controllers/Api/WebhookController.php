<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateVpsStatsJob;
use App\Jobs\UpdateStreamStatusJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    /**
     * Handle VPS stats webhook from agent.py
     */
    public function handleVpsStats(Request $request)
    {
        try {
            $data = $request->all();
            
            // Validate required fields
            if (!isset($data['vps_id'])) {
                return response()->json(['error' => 'Missing vps_id'], 400);
            }
            
            Log::info("📊 [Webhook] VPS stats received", [
                'vps_id' => $data['vps_id'],
                'cpu' => $data['cpu_usage'] ?? 'N/A',
                'ram' => $data['ram_usage'] ?? 'N/A',
                'streams' => $data['active_streams'] ?? 'N/A'
            ]);
            
            // Store directly in Redis (bypass queue for real-time)
            $data['received_at'] = now()->timestamp;
            Redis::hset('vps_live_stats', $data['vps_id'], json_encode($data));
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error("❌ [Webhook] VPS stats failed", [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Handle stream status webhook from agent.py
     */
    public function handleStreamStatus(Request $request)
    {
        try {
            $data = $request->all();
            
            // Validate required fields
            if (!isset($data['stream_id'])) {
                return response()->json(['error' => 'Missing stream_id'], 400);
            }
            
            Log::info("🎬 [Webhook] Stream status received", [
                'stream_id' => $data['stream_id'],
                'status' => $data['status'] ?? $data['type'] ?? 'N/A'
            ]);
            
            // Dispatch job to handle stream status
            UpdateStreamStatusJob::dispatch($data);
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error("❌ [Webhook] Stream status failed", [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Handle batch VPS stats (for high scale)
     */
    public function batchVpsStats(Request $request)
    {
        try {
            $data = $request->all();
            $updates = $data['updates'] ?? [];
            
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }
            
            $processed = 0;
            foreach ($updates as $vpsStats) {
                if (isset($vpsStats['vps_id'])) {
                    $vpsStats['received_at'] = now()->timestamp;
                    Redis::hset('vps_live_stats', $vpsStats['vps_id'], json_encode($vpsStats));
                    $processed++;
                }
            }
            
            Log::info("📊 [Webhook] Batch VPS stats processed", [
                'total_updates' => count($updates),
                'processed' => $processed
            ]);
            
            return response()->json([
                'success' => true,
                'processed' => $processed
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ [Webhook] Batch VPS stats failed", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Handle batch stream events (for high scale)
     */
    public function batchStreamEvents(Request $request)
    {
        try {
            $data = $request->all();
            $events = $data['events'] ?? [];
            
            if (empty($events)) {
                return response()->json(['error' => 'No events provided'], 400);
            }
            
            foreach ($events as $event) {
                if (isset($event['stream_id'])) {
                    UpdateStreamStatusJob::dispatch($event);
                }
            }
            
            Log::info("🎬 [Webhook] Batch stream events processed", [
                'total_events' => count($events)
            ]);
            
            return response()->json([
                'success' => true,
                'processed' => count($events)
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ [Webhook] Batch stream events failed", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Health check endpoint
     */
    public function handleHealthCheck(Request $request)
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }
    
    /**
     * System statistics (admin only)
     */
    public function systemStats(Request $request)
    {
        try {
            $stats = [
                'redis_stats' => Redis::hlen('vps_live_stats'),
                'active_vps' => Redis::hgetall('vps_live_stats'),
                'timestamp' => now()->toISOString()
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get stats'], 500);
        }
    }
}
