<?php

namespace App\Services;

use App\Models\License;
use App\Models\ToolOrder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Generate a unique license key
     */
    public function generateLicenseKey(): string
    {
        do {
            $key = strtoupper(
                Str::random(4) . '-' . 
                Str::random(4) . '-' . 
                Str::random(4) . '-' . 
                Str::random(4)
            );
        } while (License::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * Create license for a tool order
     */
    public function createLicenseForOrder(ToolOrder $toolOrder): License
    {
        if ($toolOrder->license_key) {
            // License already exists
            return License::where('license_key', $toolOrder->license_key)->first();
        }

        $licenseKey = $this->generateLicenseKey();

        $license = License::create([
            'user_id' => $toolOrder->user_id,
            'tool_id' => $toolOrder->tool_id,
            'license_key' => $licenseKey,
            'is_active' => true,
            'expires_at' => null, // Lifetime license for now
        ]);

        // Update tool order with license key
        $toolOrder->update(['license_key' => $licenseKey]);

        Log::info('License created for tool order', [
            'license_id' => $license->id,
            'tool_order_id' => $toolOrder->id,
            'license_key' => $licenseKey,
            'tool' => $toolOrder->tool->name,
            'user' => $toolOrder->user->email
        ]);

        return $license;
    }

    /**
     * Activate license on a device
     */
    public function activateLicense(License $license, array $deviceInfo): bool
    {
        try {
            // Check if already activated on another device
            if ($license->device_id && $license->device_id !== $deviceInfo['device_id']) {
                return false;
            }

            $license->update([
                'device_id' => $deviceInfo['device_id'],
                'device_name' => $deviceInfo['device_name'],
                'device_info' => $deviceInfo,
                'activated_at' => now()
            ]);

            Log::info('License activated', [
                'license_id' => $license->id,
                'device_id' => $deviceInfo['device_id'],
                'device_name' => $deviceInfo['device_name']
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('License activation failed', [
                'license_id' => $license->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Deactivate license from current device
     */
    public function deactivateLicense(License $license): bool
    {
        try {
            $license->update([
                'device_id' => null,
                'device_name' => null,
                'device_info' => null,
                'activated_at' => null
            ]);

            // Delete activation records
            $license->activations()->delete();

            Log::info('License deactivated', [
                'license_id' => $license->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('License deactivation failed', [
                'license_id' => $license->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if license is valid and active
     */
    public function isLicenseValid(License $license, string $deviceId = null): array
    {
        $result = [
            'valid' => false,
            'message' => '',
            'data' => null
        ];

        // Check if license is active
        if (!$license->is_active) {
            $result['message'] = 'License is inactive';
            return $result;
        }

        // Check if license is expired
        if ($license->is_expired) {
            $result['message'] = 'License has expired';
            return $result;
        }

        // Check device binding if device_id is provided
        if ($deviceId && $license->device_id && $license->device_id !== $deviceId) {
            $result['message'] = 'License is activated on another device';
            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'License is valid';
        $result['data'] = [
            'license_key' => $license->license_key,
            'tool' => [
                'id' => $license->tool->id,
                'name' => $license->tool->name
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
        ];

        return $result;
    }

    /**
     * Get license statistics for admin
     */
    public function getLicenseStats(): array
    {
        return [
            'total_licenses' => License::count(),
            'active_licenses' => License::where('is_active', true)->count(),
            'activated_licenses' => License::whereNotNull('activated_at')->count(),
            'expired_licenses' => License::where('expires_at', '<', now())->count(),
            'licenses_by_tool' => License::with('tool')
                ->selectRaw('tool_id, count(*) as count')
                ->groupBy('tool_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'tool_name' => $item->tool->name,
                        'count' => $item->count
                    ];
                })
        ];
    }

    /**
     * Bulk create licenses for multiple orders
     */
    public function bulkCreateLicenses(array $toolOrderIds): array
    {
        $results = [];
        
        foreach ($toolOrderIds as $orderId) {
            try {
                $toolOrder = ToolOrder::find($orderId);
                if ($toolOrder && !$toolOrder->license_key) {
                    $license = $this->createLicenseForOrder($toolOrder);
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => true,
                        'license_key' => $license->license_key
                    ];
                } else {
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => false,
                        'message' => 'Order not found or license already exists'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'order_id' => $orderId,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Revoke license (admin function)
     */
    public function revokeLicense(License $license, string $reason = ''): bool
    {
        try {
            $license->update([
                'is_active' => false,
                'device_id' => null,
                'device_name' => null,
                'device_info' => null,
                'activated_at' => null
            ]);

            // Delete activation records
            $license->activations()->delete();

            Log::warning('License revoked by admin', [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'reason' => $reason,
                'tool' => $license->tool->name,
                'user' => $license->user->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('License revocation failed', [
                'license_id' => $license->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
