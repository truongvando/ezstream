<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        if (!$user) {
            Log::error('FileUpload: User not authenticated');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('FileUpload: Generate upload URL request', [
            'user_id' => $user->id,
            'request_data' => $request->all()
        ]);

        try {
            // 1. Basic validation - width, height optional
            $validated = $request->validate([
                'filename' => 'required|string|max:255',
                'content_type' => 'required|string',
                'size' => 'required|integer|min:1',
                'width' => 'nullable|integer|min:1',
                'height' => 'nullable|integer|min:1',
            ]);

            Log::info('FileUpload: Validation passed', ['validated_data' => $validated]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('FileUpload: Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 400);
        }

        try {
            // 2. Check file format - Only MP4
            if ($request->content_type !== 'video/mp4') {
                Log::error('FileUpload: Invalid content type', ['content_type' => $request->content_type]);
                return response()->json([
                    'error' => 'Chỉ hỗ trợ file MP4. Vui lòng chuyển đổi video sang định dạng MP4 trước khi upload.'
                ], 400);
            }

            // 3. Check file size based on user role
            $maxSize = $user->hasRole('admin') ? 10737418240 : 10737418240; // 10GB for both
            if ($request->size > $maxSize) {
                $maxSizeGB = $maxSize / 1024 / 1024 / 1024;
                Log::error('FileUpload: File too large', ['size' => $request->size, 'max_size' => $maxSize]);
                return response()->json([
                    'error' => "File quá lớn. Tối đa {$maxSizeGB}GB."
                ], 400);
            }

            Log::info('FileUpload: Basic checks passed', ['content_type' => $request->content_type, 'size' => $request->size]);

            // 4. Check video resolution for non-admin users
            if (!$user->hasRole('admin')) {
                Log::info('FileUpload: Checking dimensions', [
                    'width' => $request->width,
                    'height' => $request->height,
                    'width_type' => gettype($request->width),
                    'height_type' => gettype($request->height),
                    'width_empty' => empty($request->width),
                    'height_empty' => empty($request->height)
                ]);

                // Require dimensions for video files
                if (!$request->width || !$request->height) {
                    Log::error('FileUpload: Missing dimensions', [
                        'width' => $request->width,
                        'height' => $request->height
                    ]);
                    return response()->json([
                        'error' => 'Không thể đọc thông tin video. File có thể bị lỗi hoặc không hợp lệ.'
                    ], 400);
                }
                try {
                    $package = $user->currentPackage();
                    Log::info('FileUpload: Package check', [
                        'package' => $package ? $package->toArray() : null,
                        'has_package' => !!$package
                    ]);
                } catch (\Exception $e) {
                    Log::error('FileUpload: Error getting currentPackage', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'error' => 'Lỗi hệ thống khi kiểm tra gói dịch vụ.'
                    ], 500);
                }

                if (!$package) {
                    Log::error('FileUpload: No package found for user', ['user_id' => $user->id]);
                    return response()->json([
                        'error' => '❌ Không thể upload video',
                        'reason' => 'Chưa có gói dịch vụ',
                        'details' => [
                            'message' => 'Tài khoản của bạn chưa có gói dịch vụ nào được kích hoạt'
                        ],
                        'solutions' => [
                            '📦 Đăng ký gói dịch vụ phù hợp với nhu cầu',
                            '💳 Thanh toán để kích hoạt gói đã chọn',
                            '📞 Liên hệ support nếu đã thanh toán nhưng chưa được kích hoạt'
                        ]
                    ], 400);
                }

                // Check if package has resolution limits
                if ($package->max_video_width && $package->max_video_height) {
                    // Check resolution - Support both landscape and portrait orientations
                    $maxWidth = $package->max_video_width;
                    $maxHeight = $package->max_video_height;

                    // Check if video exceeds limits in both orientations
                    $landscapeValid = ($request->width <= $maxWidth && $request->height <= $maxHeight);
                    $portraitValid = ($request->width <= $maxHeight && $request->height <= $maxWidth);

                    if (!$landscapeValid && !$portraitValid) {
                        $currentRes = $this->getResolutionName($request->width, $request->height);
                        $maxRes = $this->getResolutionName($maxWidth, $maxHeight);

                        Log::error('FileUpload: Resolution exceeds package limits', [
                            'user_id' => $user->id,
                            'package_name' => $package->name,
                            'video_resolution' => "{$request->width}x{$request->height}",
                            'package_limit' => "{$maxWidth}x{$maxHeight}",
                            'current_res_name' => $currentRes,
                            'max_res_name' => $maxRes
                        ]);

                        return response()->json([
                            'error' => "❌ Video không thể upload",
                            'reason' => "Độ phân giải vượt quá giới hạn gói",
                            'details' => [
                                'video_resolution' => "{$request->width}x{$request->height} ({$currentRes})",
                                'package_name' => $package->name,
                                'package_limit' => "{$maxWidth}x{$maxHeight} ({$maxRes})",
                                'supported_orientations' => "Cả video ngang và dọc đều được hỗ trợ trong giới hạn này"
                            ],
                            'solutions' => [
                                "🔧 Giảm chất lượng video xuống {$maxRes} hoặc thấp hơn",
                                "📈 Nâng cấp lên gói cao hơn để hỗ trợ {$currentRes}",
                                "✂️ Sử dụng phần mềm như HandBrake để resize video"
                            ],
                            'show_modal' => true // Flag để frontend biết hiển thị modal
                        ], 400);
                    }
                }
            }

            // 5. Check storage limit for non-admin users
            if (!$user->hasRole('admin')) {
                $storageUsage = $user->files()->sum('size');
                $package = $user->currentPackage();
                $storageLimit = $package ? $package->storage_limit_gb * 1024 * 1024 * 1024 : 5 * 1024 * 1024 * 1024;

                if (($storageUsage + $request->size) > $storageLimit) {
                    $storageUsedGB = round($storageUsage / 1024 / 1024 / 1024, 2);
                    $storageLimitGB = round($storageLimit / 1024 / 1024 / 1024, 2);
                    $fileSizeGB = round($request->size / 1024 / 1024 / 1024, 2);
                    $remainingGB = round(($storageLimit - $storageUsage) / 1024 / 1024 / 1024, 2);

                    Log::error('FileUpload: Storage limit exceeded', [
                        'user_id' => $user->id,
                        'storage_used_gb' => $storageUsedGB,
                        'storage_limit_gb' => $storageLimitGB,
                        'file_size_gb' => $fileSizeGB,
                        'remaining_gb' => $remainingGB
                    ]);

                    return response()->json([
                        'error' => '❌ Không thể upload video',
                        'reason' => 'Vượt quá giới hạn dung lượng lưu trữ',
                        'details' => [
                            'storage_used' => "{$storageUsedGB}GB / {$storageLimitGB}GB",
                            'file_size' => "{$fileSizeGB}GB",
                            'remaining_space' => "{$remainingGB}GB",
                            'package_name' => $package->name ?? 'Không xác định'
                        ],
                        'solutions' => [
                            "🗑️ Xóa bớt {$fileSizeGB}GB file cũ để có đủ dung lượng",
                            "📈 Nâng cấp lên gói có dung lượng lưu trữ cao hơn",
                            "📁 Kiểm tra và xóa các file không cần thiết"
                        ],
                        'show_modal' => true // Flag để frontend biết hiển thị modal
                    ], 400);
                }
            }

            // Generate upload URL
            $uploadData = $this->bunnyService->generateUploadUrl(
                $request->filename,
                Auth::id(),
                $request->size
            );

            // Check if upload URL generation was successful
            if (!$uploadData['success']) {
                return response()->json([
                    'error' => $uploadData['error'] ?? 'Failed to generate upload URL'
                ], 500);
            }

            $response = [
                'status' => 'success',
                'upload_url' => $uploadData['upload_url'],
                'upload_token' => $uploadData['upload_token'],
                'path' => $uploadData['remote_path'],
                'access_key' => $uploadData['access_key'] ?? null,
                'method' => $uploadData['method'] ?? 'PUT',
                'storage_mode' => $uploadData['storage_mode'] ?? 'cdn'
            ];

            // Add TUS-specific fields if method is TUS
            if (($uploadData['method'] ?? '') === 'TUS') {
                $response['video_id'] = $uploadData['video_id'] ?? null;
                $response['library_id'] = $uploadData['library_id'] ?? null;
                $response['auth_signature'] = $uploadData['auth_signature'] ?? null;
                $response['auth_expire'] = $uploadData['auth_expire'] ?? null;
                $response['upload_token'] = $uploadData['upload_token'] ?? null; // Add upload_token for confirmation
            }

            return response()->json($response);

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
        $user = Auth::user();

        Log::info("Confirm upload request received", [
            'user_id' => $user->id,
            'user_role' => $user->hasRole('admin') ? 'admin' : 'user',
            'upload_token' => $request->upload_token,
            'size' => $request->size,
            'content_type' => $request->content_type
        ]);

        $request->validate([
            'upload_token' => 'required|string',
            'size' => 'required|integer|min:1',
            'content_type' => 'required|string',
            'auto_delete_after_stream' => 'boolean',
        ]);

        try {
            // Use service to confirm upload and get file info
            $result = $this->bunnyService->confirmUpload(
                $request->upload_token,
                $request->size,
                $request->content_type,
                $request->boolean('auto_delete_after_stream', false)
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

    // Server upload method removed - only Stream Library is supported

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