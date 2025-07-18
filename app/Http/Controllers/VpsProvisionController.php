<?php

namespace App\Http\Controllers;

use App\Models\VpsServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VPS Provision Controller - Temporary controller to avoid route errors
 * This handles VPS provisioning related routes
 */
class VpsProvisionController extends Controller
{
    /**
     * Get provision script for VPS
     */
    public function getScript($token)
    {
        // For now, return a simple response
        return response()->json([
            'success' => false,
            'message' => 'VPS provisioning script not implemented yet'
        ], 501);
    }

    /**
     * Finish provisioning process
     */
    public function finish($token)
    {
        // For now, return a simple response
        return response()->json([
            'success' => false,
            'message' => 'VPS provisioning finish not implemented yet'
        ], 501);
    }

    /**
     * Get provision status page
     */
    public static function getProvisionStatusPage(VpsServer $vps)
    {
        return response()->json([
            'vps_id' => $vps->id,
            'vps_name' => $vps->name,
            'status' => $vps->status,
            'message' => 'Provision status page - implementation pending'
        ]);
    }
}
