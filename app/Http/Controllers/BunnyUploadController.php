<?php

namespace App\Http\Controllers;

use App\Services\BunnyDirectUploadService;
use App\Services\FileSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BunnyUploadController extends Controller
{
    protected $bunnyDirectUploadService;
    protected $fileSecurityService;

    public function __construct(
        BunnyDirectUploadService $bunnyDirectUploadService,
        FileSecurityService $fileSecurityService
    ) {
        $this->bunnyDirectUploadService = $bunnyDirectUploadService;
        $this->fileSecurityService = $fileSecurityService;
    }

    /**
     * Generate upload URL for Bunny.net direct upload
     */
    public function generateUploadUrl(Request $request)
    {
        try {
            $request->validate([
                'file_name' => 'required|string|max:255',
                'file_size' => 'required|integer|min:1|max:21474836480', // 20GB max
                'mime_type' => 'required|string'
            ]);

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $fileName = $request->input('file_name');
            $fileSize = $request->input('file_size');
            $mimeType = $request->input('mime_type');

            Log::info('Direct upload URL requested', [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ]);

            // Security validation
            $sanitizedFileName = $this->fileSecurityService->sanitizeFileName($fileName);
            if (!$sanitizedFileName) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tên file không hợp lệ hoặc chứa ký tự nguy hiểm.'
                ], 400);
            }

            // Validate file type
            if (!$this->fileSecurityService->isAllowedMimeType($mimeType)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Loại file không được phép.'
                ], 400);
            }

            // Check user storage quota
            if (!$user->isAdmin()) {
                $currentUsage = $user->files()->sum('size');
                $activeSubscription = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->first();

                if (!$activeSubscription || !$activeSubscription->servicePackage) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ đang hoạt động để upload file.'
                    ], 403);
                }

                $storageLimit = $activeSubscription->servicePackage->storage_limit_gb * 1024 * 1024 * 1024;

                if (($currentUsage + $fileSize) > $storageLimit) {
                    $remaining = $storageLimit - $currentUsage;
                    $remainingGB = round($remaining / (1024 * 1024 * 1024), 2);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Vượt quá hạn mức lưu trữ. Còn lại: {$remainingGB}GB"
                    ], 403);
                }
            }

            // Generate upload URL
            $result = $this->bunnyDirectUploadService->generateUploadUrl(
                $sanitizedFileName,
                $user->id,
                $fileSize
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'upload_url' => $result['upload_url'],
                    'upload_token' => $result['upload_token'],
                    'access_key' => $result['access_key'],
                    'expires_at' => $result['expires_at'],
                    'max_file_size' => $result['max_file_size']
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate upload URL: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo URL upload.'
            ], 500);
        }
    }

    /**
     * Confirm upload completion
     */
    public function confirmUpload(Request $request)
    {
        try {
            $request->validate([
                'upload_token' => 'required|string',
                'file_size' => 'required|integer|min:1',
                'mime_type' => 'required|string'
            ]);

            $uploadToken = $request->input('upload_token');
            $fileSize = $request->input('file_size');
            $mimeType = $request->input('mime_type');

            Log::info('Upload confirmation requested', [
                'upload_token' => $uploadToken,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ]);

            $result = $this->bunnyDirectUploadService->confirmUpload($uploadToken, $fileSize, $mimeType);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Upload thành công!',
                    'file_id' => $result['file_id'],
                    'file_name' => $result['file_name'],
                    'file_size' => $result['file_size'],
                    'cdn_url' => $result['cdn_url']
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to confirm upload: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xác nhận upload.'
            ], 500);
        }
    }
}
