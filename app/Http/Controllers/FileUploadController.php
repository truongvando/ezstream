<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BunnyDirectUploadService;
use App\Services\VideoValidationService;
use App\Models\UserFile;

class FileUploadController extends Controller
{
    private $bunnyService;
    private $videoValidationService;

    public function __construct(BunnyDirectUploadService $bunnyService, VideoValidationService $videoValidationService)
    {
        $this->bunnyService = $bunnyService;
        $this->videoValidationService = $videoValidationService;
    }

    /**
     * Generate upload URL for direct upload to Bunny CDN
     */
    public function generateUploadUrl(Request $request)
    {
        $user = Auth::user();

        // 1. Basic validation
        $request->validate([
            'filename' => 'required|string|max:255',
            'content_type' => 'required|string',
            'size' => 'required|integer|min:1',
            'width' => 'required|integer|min:1',
            'height' => 'required|integer|min:1',
        ]);

        try {
            // 2. Check file format - Only MP4
            if ($request->content_type !== 'video/mp4') {
                return response()->json([
                    'error' => 'Chỉ hỗ trợ file MP4. Vui lòng chuyển đổi video sang định dạng MP4 trước khi upload.'
                ], 400);
            }

            // 3. Check file size based on user role
            $maxSize = $user->hasRole('admin') ? 10737418240 : 10737418240; // 10GB for both
            if ($request->size > $maxSize) {
                $maxSizeGB = $maxSize / 1024 / 1024 / 1024;
                return response()->json([
                    'error' => "File quá lớn. Tối đa {$maxSizeGB}GB."
                ], 400);
            }

            // 4. Check video resolution for non-admin users
            if (!$user->hasRole('admin')) {
                // Validate dimensions are provided
                if (!$request->width || !$request->height) {
                    return response()->json([
                        'error' => 'Không thể đọc thông tin video. Vui lòng thử lại.'
                    ], 400);
                }

                $package = $user->currentPackage();
                if (!$package) {
                    return response()->json([
                        'error' => 'Không có gói dịch vụ. Vui lòng đăng ký gói để upload video.'
                    ], 400);
                }

                // Check if package has resolution limits
                if (!$package->max_video_width || !$package->max_video_height) {
                    return response()->json([
                        'error' => 'Gói dịch vụ chưa được cấu hình đúng. Vui lòng liên hệ admin.'
                    ], 400);
                }

                // Check resolution - Support both landscape and portrait orientations
                $maxWidth = $package->max_video_width;
                $maxHeight = $package->max_video_height;

                // Check if video exceeds limits in both orientations
                $landscapeValid = ($request->width <= $maxWidth && $request->height <= $maxHeight);
                $portraitValid = ($request->width <= $maxHeight && $request->height <= $maxWidth);

                if (!$landscapeValid && !$portraitValid) {
                    $currentRes = $this->getResolutionName($request->width, $request->height);
                    $maxRes = $this->getResolutionName($maxWidth, $maxHeight);

                    return response()->json([
                        'error' => "Video có độ phân giải {$currentRes} ({$request->width}x{$request->height}) vượt quá giới hạn gói {$maxRes} ({$maxWidth}x{$maxHeight}). Gói này hỗ trợ cả video ngang và dọc trong giới hạn này. Vui lòng nâng cấp gói hoặc giảm chất lượng video."
                    ], 400);
                }
            }

            // 5. Check storage limit for non-admin users
            if (!$user->hasRole('admin')) {
                $storageUsage = $user->files()->sum('size');
                $package = $user->currentPackage();
                $storageLimit = $package ? $package->storage_limit_gb * 1024 * 1024 * 1024 : 5 * 1024 * 1024 * 1024;

                if (($storageUsage + $request->size) > $storageLimit) {
                    return response()->json([
                        'error' => 'Không đủ dung lượng lưu trữ. Vui lòng nâng cấp gói hoặc xóa bớt file.'
                    ], 400);
                }
            }

            // Generate upload URL
            $uploadData = $this->bunnyService->generateUploadUrl(
                $request->filename,
                Auth::id(),
                $request->size
            );

            return response()->json([
                'status' => 'success',
                'upload_url' => $uploadData['upload_url'],
                'upload_token' => $uploadData['upload_token'],
                'path' => $uploadData['remote_path'],
                'access_key' => $uploadData['access_key'],
                'method' => 'PUT'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate upload URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm upload completion and save to database
     */
    public function confirmUpload(Request $request)
    {
        $request->validate([
            'upload_token' => 'required|string',
            'size' => 'required|integer|min:1',
            'content_type' => 'required|string',
        ]);

        try {
            // Use service to confirm upload and get file info
            $result = $this->bunnyService->confirmUpload(
                $request->upload_token,
                $request->size,
                $request->content_type
            );

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message'] ?? 'Upload confirmation failed'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded successfully',
                'file' => [
                    'id' => $result['file_id'],
                    'name' => $result['file_name'],
                    'size' => $result['file_size'],
                    'url' => $result['cdn_url']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to confirm upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download file temporarily for validation
     */
    private function downloadFileForValidation(string $url): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'video_validation_');
            $fileContent = file_get_contents($url);

            if ($fileContent === false) {
                return null;
            }

            file_put_contents($tempFile, $fileContent);
            return $tempFile;

        } catch (\Exception $e) {
            Log::error('Failed to download file for validation: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get resolution name from dimensions
     */
    private function getResolutionName(?int $width, ?int $height): string
    {
        if (!$width || !$height) return 'Unknown';

        if ($width >= 3840 && $height >= 2160) return '4K UHD';
        if ($width >= 2560 && $height >= 1440) return '2K QHD';
        if ($width >= 1920 && $height >= 1080) return 'Full HD 1080p';
        if ($width >= 1280 && $height >= 720) return 'HD 720p';
        if ($width >= 854 && $height >= 480) return 'SD 480p';
        return 'Low Quality';
    }
}