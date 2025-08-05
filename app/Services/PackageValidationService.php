<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PackageValidationService
{
    /**
     * Validate if user can upload file based on package limits
     */
    public function validateUpload(User $user, array $fileData): array
    {
        Log::info('🔍 [PackageValidation] Validating upload for user', [
            'user_id' => $user->id,
            'file_size' => $fileData['size'],
            'file_name' => $fileData['name']
        ]);

        // Admin users bypass all limits
        if ($user->isAdmin()) {
            Log::info('👑 [PackageValidation] Admin user - bypassing all limits');
            return [
                'allowed' => true,
                'package_info' => [
                    'is_admin' => true,
                    'package_name' => 'Admin',
                    'storage_limit' => 'Unlimited',
                    'max_video_quality' => 'Unlimited'
                ]
            ];
        }

        // Get user's current package
        $package = $user->currentPackage();
        
        if (!$package) {
            Log::warning('⚠️ [PackageValidation] User has no active package');
            return [
                'allowed' => false,
                'error' => 'Bạn cần đăng ký gói dịch vụ để upload file.',
                'package_info' => null
            ];
        }

        // Check storage limit
        $storageValidation = $this->validateStorageLimit($user, $package, $fileData['size']);
        if (!$storageValidation['allowed']) {
            return $storageValidation;
        }

        // Check file size limit (if package has one)
        $fileSizeValidation = $this->validateFileSize($package, $fileData['size']);
        if (!$fileSizeValidation['allowed']) {
            return $fileSizeValidation;
        }

        // Check video quality limits (for video files)
        if (str_starts_with($fileData['type'], 'video/')) {
            $qualityValidation = $this->validateVideoQuality($package, $fileData);
            if (!$qualityValidation['allowed']) {
                return $qualityValidation;
            }
        }

        Log::info('✅ [PackageValidation] Upload validation passed');
        
        return [
            'allowed' => true,
            'package_info' => [
                'package_name' => $package->name,
                'storage_limit_gb' => $package->storage_limit_gb,
                'max_video_width' => $package->max_video_width,
                'max_video_height' => $package->max_video_height,
                'max_file_size_gb' => $package->max_file_size_gb ?? null
            ]
        ];
    }

    /**
     * Validate storage limit
     */
    private function validateStorageLimit(User $user, $package, int $fileSize): array
    {
        $currentUsage = $user->files()->sum('size');
        $storageLimit = $package->storage_limit_gb * 1024 * 1024 * 1024; // Convert GB to bytes
        $availableSpace = $storageLimit - $currentUsage;

        Log::info('📊 [PackageValidation] Storage check', [
            'current_usage' => $currentUsage,
            'storage_limit' => $storageLimit,
            'available_space' => $availableSpace,
            'file_size' => $fileSize
        ]);

        if ($fileSize > $availableSpace) {
            $usedGB = round($currentUsage / (1024 * 1024 * 1024), 2);
            $limitGB = $package->storage_limit_gb;
            $neededGB = round($fileSize / (1024 * 1024 * 1024), 2);
            
            return [
                'allowed' => false,
                'error' => "Không đủ dung lượng lưu trữ. Đã sử dụng: {$usedGB}GB/{$limitGB}GB. File cần: {$neededGB}GB.",
                'package_info' => null
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Validate individual file size limit
     */
    private function validateFileSize($package, int $fileSize): array
    {
        if ($package->max_file_size_gb) {
            $maxFileSize = $package->max_file_size_gb * 1024 * 1024 * 1024; // Convert GB to bytes
            
            if ($fileSize > $maxFileSize) {
                $fileSizeGB = round($fileSize / (1024 * 1024 * 1024), 2);
                
                return [
                    'allowed' => false,
                    'error' => "File quá lớn. Tối đa: {$package->max_file_size_gb}GB. File của bạn: {$fileSizeGB}GB.",
                    'package_info' => null
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Validate video quality limits
     */
    private function validateVideoQuality($package, array $fileData): array
    {
        // Note: We can't check video resolution from file metadata in browser
        // This validation would need to be done after upload or with additional metadata
        
        Log::info('📹 [PackageValidation] Video quality limits', [
            'max_width' => $package->max_video_width,
            'max_height' => $package->max_video_height
        ]);

        // For now, we'll just log the limits and allow upload
        // The actual resolution check should be done during video processing
        
        return ['allowed' => true];
    }

    /**
     * Get user's storage usage summary
     */
    public function getStorageUsage(User $user): array
    {
        $package = $user->currentPackage();
        $currentUsage = $user->files()->sum('size');
        
        if (!$package) {
            return [
                'has_package' => false,
                'current_usage' => $currentUsage,
                'storage_limit' => 0,
                'available_space' => 0,
                'usage_percentage' => 0
            ];
        }

        $storageLimit = $package->storage_limit_gb * 1024 * 1024 * 1024;
        $availableSpace = max(0, $storageLimit - $currentUsage);
        $usagePercentage = $storageLimit > 0 ? ($currentUsage / $storageLimit) * 100 : 0;

        return [
            'has_package' => true,
            'package_name' => $package->name,
            'current_usage' => $currentUsage,
            'storage_limit' => $storageLimit,
            'available_space' => $availableSpace,
            'usage_percentage' => round($usagePercentage, 1),
            'can_upload' => $availableSpace > 0
        ];
    }

    /**
     * Check if user can upload a specific file size
     */
    public function canUploadFileSize(User $user, int $fileSize): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $package = $user->currentPackage();
        if (!$package) {
            return false;
        }

        $currentUsage = $user->files()->sum('size');
        $storageLimit = $package->storage_limit_gb * 1024 * 1024 * 1024;
        $availableSpace = $storageLimit - $currentUsage;

        return $fileSize <= $availableSpace;
    }
}
