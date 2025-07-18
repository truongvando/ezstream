<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Models\ServicePackage;

class VideoValidationService
{
    protected $getID3;

    public function __construct()
    {
        $this->getID3 = new \getID3;
    }

    /**
     * Validate video resolution.
     *
     * @param string $filePath Path to the temporary video file.
     * @param int $maxWidth The maximum allowed width.
     * @param int $maxHeight The maximum allowed height.
     * @return array An array containing 'valid' (boolean) and 'reason' (string).
     */
    public function validateResolution(string $filePath, int $maxWidth, int $maxHeight): array
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'reason' => 'File not found.'];
        }

        try {
            // Analyze file with getID3
            $fileInfo = $this->getID3->analyze($filePath);

            if (!isset($fileInfo['video'])) {
                return ['valid' => false, 'reason' => 'No video stream found in the file.'];
            }

            $video = $fileInfo['video'];
            $width = $video['resolution_x'] ?? 0;
            $height = $video['resolution_y'] ?? 0;

            if ($width <= 0 || $height <= 0) {
                return ['valid' => false, 'reason' => 'Could not determine video resolution.'];
            }

            Log::info('Server-side validation with getID3', [
                'file' => $filePath,
                'detected_w' => $width,
                'detected_h' => $height,
                'max_w' => $maxWidth,
                'max_h' => $maxHeight
            ]);

            // Check if video exceeds limits (support both landscape and portrait)
            $landscapeValid = ($width <= $maxWidth && $height <= $maxHeight);
            $portraitValid = ($width <= $maxHeight && $height <= $maxWidth);

            if (!$landscapeValid && !$portraitValid) {
                return [
                    'valid' => false,
                    'reason' => "Video resolution ({$width}x{$height}) exceeds the allowed limit ({$maxWidth}x{$maxHeight})."
                ];
            }

            return ['valid' => true, 'reason' => 'Resolution is within limits.'];

        } catch (\Exception $e) {
            Log::error('Error during video validation with getID3', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return ['valid' => false, 'reason' => 'Could not analyze video file.'];
        }
    }

    /**
     * Validate uploaded video against user's package limits
     */
    public function validateVideoUpload(UploadedFile $file, User $user): array
    {
        // Admin bypass all restrictions
        if ($user->hasRole('admin')) {
            return ['valid' => true, 'reason' => 'Admin user - all restrictions bypassed'];
        }

        // Get user's current package
        $package = $user->currentPackage();
        if (!$package) {
            return [
                'valid' => false,
                'reason' => 'Không có gói dịch vụ. Vui lòng đăng ký gói để upload video.'
            ];
        }

        // Validate resolution against package limits
        return $this->validateResolution($file->getRealPath(), $package->max_video_width, $package->max_video_height);
    }

    /**
     * Get resolution name from dimensions
     */
    public function getResolutionName(?int $width, ?int $height): string
    {
        if (!$width || !$height) return 'Unknown';

        if ($width >= 3840 && $height >= 2160) return '4K UHD';
        if ($width >= 2560 && $height >= 1440) return '2K QHD';
        if ($width >= 1920 && $height >= 1080) return 'Full HD 1080p';
        if ($width >= 1280 && $height >= 720) return 'HD 720p';
        if ($width >= 854 && $height >= 480) return 'SD 480p';
        return 'Low Quality';
    }

    /**
     * Get package resolution limit as human readable
     */
    public function getPackageResolutionLimit(?ServicePackage $package): string
    {
        if (!$package) return 'HD 720p'; // Default limit

        return $this->getResolutionName($package->max_video_width, $package->max_video_height);
    }
}