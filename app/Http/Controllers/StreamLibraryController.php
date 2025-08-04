<?php

namespace App\Http\Controllers;

use App\Services\StreamLibraryUploadService;
use App\Services\BunnyStreamService;
use App\Models\UserFile;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StreamLibraryController extends Controller
{
    protected $streamLibraryUploadService;
    protected $bunnyStreamService;

    public function __construct(
        StreamLibraryUploadService $streamLibraryUploadService,
        BunnyStreamService $bunnyStreamService
    ) {
        $this->streamLibraryUploadService = $streamLibraryUploadService;
        $this->bunnyStreamService = $bunnyStreamService;
    }

    /**
     * Generate upload URL for Stream Library
     */
    public function generateUploadUrl(Request $request)
    {
        try {
            $request->validate([
                'filename' => 'required|string|max:255',
                'size' => 'required|integer|min:1|max:21474836480', // 20GB max
                'content_type' => 'required|string'
            ]);

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if SRS streaming is enabled
            $streamingMethod = Setting::where('key', 'streaming_method')->value('value') ?? 'ffmpeg_copy';
            if ($streamingMethod !== 'srs') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stream Library upload only available when SRS streaming is enabled'
                ], 400);
            }

            $fileName = $request->input('filename');
            $fileSize = $request->input('size');
            $mimeType = $request->input('content_type');

            Log::info('Stream Library upload URL requested', [
                'user_id' => $user->id,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ]);

            $result = $this->streamLibraryUploadService->generateUploadUrl(
                $fileName,
                $fileSize,
                $mimeType,
                $user->id
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'upload_token' => $result['upload_token'],
                    'upload_method' => 'stream_library',
                    'expires_at' => $result['expires_at'],
                    'max_file_size' => $result['max_file_size'],
                    'instructions' => $result['instructions']
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate Stream Library upload URL: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo URL upload.'
            ], 500);
        }
    }

    /**
     * Handle file upload to Stream Library
     */
    public function uploadFile(Request $request)
    {
        try {
            $request->validate([
                'upload_token' => 'required|string',
                'file' => 'required|file|max:20971520' // 20GB in KB
            ]);

            $uploadToken = $request->input('upload_token');
            $file = $request->file('file');

            Log::info('Stream Library file upload received', [
                'upload_token' => $uploadToken,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);

            // Store file temporarily
            $tempPath = $file->store('temp', 'local');
            $fullTempPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path('app' . DIRECTORY_SEPARATOR . $tempPath));

            // Confirm upload and process to Stream Library
            $result = $this->streamLibraryUploadService->confirmStreamUpload(
                $uploadToken,
                $fullTempPath,
                $file->getSize()
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'file_id' => $result['file_id'],
                    'file_name' => $result['file_name'],
                    'video_id' => $result['video_id'],
                    'hls_url' => $result['hls_url'],
                    'mp4_url' => $result['mp4_url'],
                    'thumbnail_url' => $result['thumbnail_url']
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Stream Library file upload failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Upload thất bại.'
            ], 500);
        }
    }

    /**
     * Check video processing status
     */
    public function checkStatus(Request $request)
    {
        try {
            $request->validate([
                'video_id' => 'required|string'
            ]);

            $videoId = $request->input('video_id');
            $result = $this->streamLibraryUploadService->checkProcessingStatus($videoId);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check processing status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể kiểm tra trạng thái.'
            ], 500);
        }
    }

    /**
     * Migrate existing file to Stream Library
     */
    public function migrateFile(Request $request)
    {
        try {
            $request->validate([
                'file_id' => 'required|integer'
            ]);

            $user = Auth::user();
            $fileId = $request->input('file_id');

            $userFile = $user->files()->find($fileId);
            if (!$userFile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File không tồn tại.'
                ], 404);
            }

            Log::info('Migrating file to Stream Library', [
                'user_id' => $user->id,
                'file_id' => $fileId,
                'file_name' => $userFile->original_name
            ]);

            $result = $this->streamLibraryUploadService->migrateFromStorage($userFile);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'video_id' => $result['video_id'] ?? null,
                    'hls_url' => $result['hls_url'] ?? null
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('File migration failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Migration thất bại.'
            ], 500);
        }
    }

    /**
     * Test Stream Library connection
     */
    public function testConnection()
    {
        try {
            $result = $this->bunnyStreamService->testConnection();

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? $result['message'] : $result['error'],
                'data' => $result['data'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Stream Library connection test failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed.'
            ], 500);
        }
    }

    /**
     * Get library statistics
     */
    public function getStats()
    {
        try {
            $user = Auth::user();
            if (!$user->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $result = $this->bunnyStreamService->getLibraryStats();

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get library stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get statistics.'
            ], 500);
        }
    }
}
