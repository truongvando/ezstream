<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    /**
     * Get API configuration for Python clients
     */
    public function config()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'api_base_url' => config('app.url') . '/api',
                'app_name' => config('app.name'),
                'version' => '1.0.0',
                'endpoints' => [
                    'verify' => '/license/verify',
                    'check_status' => '/license/check-status',
                    'deactivate' => '/license/deactivate',
                ],
                'timeout' => 30,
                'retry_attempts' => 3,
            ]
        ]);
    }

    /**
     * Verify and activate a license
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'device_id' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'device_info' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $license = License::where('license_key', $request->license_key)
                             ->where('is_active', true)
                             ->with(['tool', 'user'])
                             ->first();

            if (!$license) {
                Log::warning('License verification failed: License not found', [
                    'license_key' => $request->license_key,
                    'device_id' => $request->device_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'License key not found or inactive'
                ], 404);
            }

            // Check if license is expired
            if ($license->is_expired) {
                Log::warning('License verification failed: License expired', [
                    'license_key' => $request->license_key,
                    'expires_at' => $license->expires_at
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'License has expired'
                ], 403);
            }

            // Check if license is already activated on a different device
            if ($license->device_id && $license->device_id !== $request->device_id) {
                Log::warning('License verification failed: Already activated on different device', [
                    'license_key' => $request->license_key,
                    'current_device' => $license->device_id,
                    'requested_device' => $request->device_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'License is already activated on another device',
                    'current_device' => $license->device_name
                ], 409);
            }

            // Activate license if not already activated on this device
            if (!$license->device_id || $license->device_id !== $request->device_id) {
                $license->update([
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name,
                    'device_info' => $request->device_info,
                    'activated_at' => now()
                ]);

                // Create activation record
                LicenseActivation::create([
                    'license_id' => $license->id,
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name,
                    'device_info' => $request->device_info,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'activated_at' => now()
                ]);

                Log::info('License activated successfully', [
                    'license_id' => $license->id,
                    'tool' => $license->tool->name,
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'License verified successfully',
                'data' => [
                    'license_key' => $license->license_key,
                    'tool' => [
                        'id' => $license->tool->id,
                        'name' => $license->tool->name,
                        'version' => '1.0.0' // You can add version field to tools table
                    ],
                    'user' => [
                        'id' => $license->user->id,
                        'name' => $license->user->name,
                        'email' => $license->user->email
                    ],
                    'activated_at' => $license->activated_at,
                    'expires_at' => $license->expires_at,
                    'device_info' => [
                        'device_id' => $license->device_id,
                        'device_name' => $license->device_name
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('License verification error', [
                'license_key' => $request->license_key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check license status
     */
    public function checkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $license = License::where('license_key', $request->license_key)
                             ->where('device_id', $request->device_id)
                             ->where('is_active', true)
                             ->with(['tool'])
                             ->first();

            if (!$license) {
                return response()->json([
                    'success' => false,
                    'message' => 'License not found or not activated on this device'
                ], 404);
            }

            // Check if license is expired
            if ($license->is_expired) {
                return response()->json([
                    'success' => false,
                    'message' => 'License has expired',
                    'expires_at' => $license->expires_at
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'License is active',
                'data' => [
                    'license_key' => $license->license_key,
                    'tool' => [
                        'id' => $license->tool->id,
                        'name' => $license->tool->name
                    ],
                    'activated_at' => $license->activated_at,
                    'expires_at' => $license->expires_at,
                    'is_active' => true,
                    'is_expired' => false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('License status check error', [
                'license_key' => $request->license_key,
                'device_id' => $request->device_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate license (for user to move to another device)
     */
    public function deactivate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $license = License::where('license_key', $request->license_key)
                             ->where('device_id', $request->device_id)
                             ->where('is_active', true)
                             ->first();

            if (!$license) {
                return response()->json([
                    'success' => false,
                    'message' => 'License not found or not activated on this device'
                ], 404);
            }

            // Deactivate license
            $license->update([
                'device_id' => null,
                'device_name' => null,
                'device_info' => null,
                'activated_at' => null
            ]);

            Log::info('License deactivated via API', [
                'license_id' => $license->id,
                'device_id' => $request->device_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'License deactivated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('License deactivation error', [
                'license_key' => $request->license_key,
                'device_id' => $request->device_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
