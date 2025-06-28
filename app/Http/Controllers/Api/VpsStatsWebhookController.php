<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpsServer;
use App\Models\VpsStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VpsStatsWebhookController extends Controller
{
    /**
     * Receive VPS stats from VPS agents
     */
    public function receiveStats(Request $request)
    {
        try {
            // Validate required fields
            $vpsId = $request->input('vps_id');
            $cpuUsage = $request->input('cpu_usage');
            $ramUsage = $request->input('ram_usage');
            $diskUsage = $request->input('disk_usage');
            $timestamp = $request->input('timestamp');
            $authToken = $request->header('X-VPS-Auth-Token');

            if (!$vpsId || !is_numeric($cpuUsage) || !is_numeric($ramUsage) || !is_numeric($diskUsage)) {
                return response()->json(['error' => 'Missing or invalid required fields'], 400);
            }

            // Find VPS
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                Log::warning("Stats webhook for unknown VPS", ['vps_id' => $vpsId]);
                return response()->json(['error' => 'VPS not found'], 404);
            }

            // Validate auth token
            $expectedToken = hash('sha256', "vps_stats_{$vpsId}_" . config('app.key'));
            if (!$authToken || $authToken !== $expectedToken) {
                Log::warning("Invalid auth token for VPS stats", [
                    'vps_id' => $vpsId,
                    'provided_token' => $authToken
                ]);
                return response()->json(['error' => 'Invalid auth token'], 403);
            }

            // Create stats record
            VpsStat::create([
                'vps_server_id' => $vpsId,
                'cpu_usage_percent' => min(100, max(0, floatval($cpuUsage))),
                'ram_usage_percent' => min(100, max(0, floatval($ramUsage))),
                'disk_usage_percent' => min(100, max(0, floatval($diskUsage))),
                'created_at' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : now(),
            ]);

            // Update VPS last seen
            $vps->update([
                'last_stats_update' => now(),
                'status' => 'ACTIVE' // Auto-mark as active when receiving stats
            ]);

            Log::info("VPS stats received via webhook", [
                'vps_id' => $vpsId,
                'cpu' => $cpuUsage,
                'ram' => $ramUsage,
                'disk' => $diskUsage
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stats received successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("VPS stats webhook error", [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Get auth token for VPS agent setup
     */
    public function getAuthToken($vpsId)
    {
        $vps = VpsServer::find($vpsId);
        if (!$vps) {
            return response()->json(['error' => 'VPS not found'], 404);
        }

        $token = hash('sha256', "vps_stats_{$vpsId}_" . config('app.key'));
        
        return response()->json([
            'vps_id' => $vpsId,
            'auth_token' => $token,
            'webhook_url' => url('/api/vps-stats'),
            'recommended_interval' => 60, // seconds
        ]);
    }
} 