<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BunnyDirectUploadService;
use App\Services\FileSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DirectUploadController extends Controller
{
    protected $directUploadService;
    protected $fileSecurityService;

    public function __construct(BunnyDirectUploadService $directUploadService, FileSecurityService $fileSecurityService)
    {
        $this->directUploadService = $directUploadService;
        $this->fileSecurityService = $fileSecurityService;
    }

    /**
     * Generate signed upload URL for direct browser upload
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
                return response()->json(['error' => 'Unauthorized'], 401);
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
                    'success' => false,
                    'error' => 'Invalid filename or contains dangerous characters'
                ], 400);
            }

            // Validate file type
            if (!$this->fileSecurityService->isAllowedMimeType($mimeType)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File type not allowed'
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
                        'success' => false,
                        'error' => 'Active subscription required for file uploads'
                    ], 403);
                }

                $storageLimit = $activeSubscription->servicePackage->storage_limit_gb * 1024 * 1024 * 1024;

                if (($currentUsage + $fileSize) > $storageLimit) {
                    $remaining = $storageLimit - $currentUsage;
                    $remainingGB = round($remaining / (1024 * 1024 * 1024), 2);
                    return response()->json([
                        'success' => false,
                        'error' => "Storage quota exceeded. Available: {$remainingGB}GB"
                    ], 403);
                }
            }

            // Generate upload URL
            $result = $this->directUploadService->generateUploadUrl(
                $sanitizedFileName,
                $user->id,
                $fileSize
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'upload_url' => $result['upload_url'],
                    'upload_token' => $result['upload_token'],
                    'access_key' => $result['access_key'],
                    'expires_at' => $result['expires_at'],
                    'max_file_size' => $result['max_file_size'],
                    'instructions' => [
                        'method' => 'PUT',
                        'headers' => [
                            'AccessKey' => $result['access_key'],
                            'Content-Type' => $mimeType
                        ],
                        'note' => 'Upload file directly to upload_url using PUT method'
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate upload URL: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate upload URL'
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

            $result = $this->directUploadService->confirmUpload($uploadToken, $fileSize, $mimeType);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'file_id' => $result['file_id'],
                    'file_name' => $result['file_name'],
                    'file_size' => $result['file_size'],
                    'cdn_url' => $result['cdn_url'],
                    'message' => 'Upload confirmed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to confirm upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to confirm upload'
            ], 500);
        }
    }

    /**
     * Get upload progress
     */
    public function getUploadProgress(Request $request, $uploadToken)
    {
        try {
            $result = $this->directUploadService->getUploadProgress($uploadToken);
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to get upload progress: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to get upload progress'
            ], 500);
        }
    }

    /**
     * Cancel upload
     */
    public function cancelUpload(Request $request, $uploadToken)
    {
        try {
            $result = $this->directUploadService->cancelUpload($uploadToken);
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to cancel upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel upload'
            ], 500);
        }
    }

    /**
     * Validate upload token
     */
    public function validateToken(Request $request, $uploadToken)
    {
        try {
            $result = $this->directUploadService->validateUploadToken($uploadToken);
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to validate upload token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to validate upload token'
            ], 500);
        }
    }
}
