<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\UserFile;

class FileUploadController extends Controller
{
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Handle standard file upload.
     * The file is temporarily stored on the server, then streamed to Google Drive.
     */
    public function uploadVideo(Request $request)
    {
        // Set reasonable time limit for large uploads
        set_time_limit(600); // 10 minutes
        
        Log::info('File upload started', ['user_id' => auth()->id()]);
        
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,avi,mkv|max:2097152', // Max 2GB in kilobytes
        ]);

        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated.'], 401);
            }

            $uploadedFile = $request->file('file');
            $fileName = $uploadedFile->getClientOriginalName();
            $tempPath = $uploadedFile->getPathname();
            $fileSize = $uploadedFile->getSize();
            
            // Check storage limit for non-admin users
            if (!$user->isAdmin()) {
                $currentUsage = $user->files()->sum('size');
                $activeSubscription = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
                    
                if (!$activeSubscription || !$activeSubscription->servicePackage) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ đang hoạt động để upload file.'
                    ], 403);
                }
                
                $storageLimit = $activeSubscription->servicePackage->storage_limit * 1024 * 1024 * 1024; // GB to bytes
                
                if (($currentUsage + $fileSize) > $storageLimit) {
                    $remaining = $storageLimit - $currentUsage;
                    $remainingGB = round($remaining / (1024 * 1024 * 1024), 2);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Vượt quá giới hạn lưu trữ. Dung lượng còn lại: {$remainingGB}GB"
                    ], 403);
                }
            }
            
            Log::info('Upload details', [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'temp_path' => $tempPath,
            ]);

            // Upload to Google Drive from the temporary path
            $result = $this->googleDriveService->uploadFile($tempPath, $fileName, $uploadedFile->getMimeType());
            
            Log::info('Google Drive upload result', ['success' => $result['success'] ?? false]);

            if ($result['success']) {
                // Create database record
                $userFile = $user->files()->create([
                    'disk' => 'google_drive',
                    'path' => null,
                    'original_name' => $fileName,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $fileSize,
                    'status' => 'AVAILABLE',
                    'google_drive_file_id' => $result['file_id']
                ]);

                Log::info('Database record created', ['user_file_id' => $userFile->id]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Upload thành công!',
                    'data' => [
                        'file_id' => $userFile->id,
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'google_drive_id' => $result['file_id']
                    ]
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error during Google Drive upload.');
            }

        } catch (Exception $e) {
            Log::error('Upload error details', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi server khi upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Initialize chunked upload
     */
    public function initChunkedUpload(Request $request)
    {
        $request->validate([
            'fileName' => 'required|string',
            'fileSize' => 'required|integer',
            'mimeType' => 'required|string'
        ]);

        try {
            $user = auth()->user();
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $mimeType = $request->mimeType;

            // Check storage limit
            if (!$user->isAdmin()) {
                $currentUsage = $user->files()->sum('size');
                $activeSubscription = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
                    
                if (!$activeSubscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ để upload file.'
                    ], 403);
                }
                
                $storageLimit = $activeSubscription->servicePackage->storage_limit * 1024 * 1024 * 1024;
                
                if (($currentUsage + $fileSize) > $storageLimit) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vượt quá giới hạn lưu trữ.'
                    ], 403);
                }
            }

            // Create upload session
            $uploadId = uniqid('upload_');
            $tempDir = storage_path('app/temp_uploads/' . $uploadId);
            
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Store upload info in session
            session([
                "upload_{$uploadId}" => [
                    'user_id' => $user->id,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                    'temp_dir' => $tempDir,
                    'chunks_received' => 0,
                    'total_chunks' => 0
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'upload_id' => $uploadId,
                'chunk_size' => 2 * 1024 * 1024 // 2MB chunks
            ]);

        } catch (Exception $e) {
            Log::error('Init chunked upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload chunk
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string',
            'chunk_index' => 'required|integer',
            'chunk' => 'required|file'
        ]);

        try {
            $uploadId = $request->upload_id;
            $chunkIndex = $request->chunk_index;
            $chunk = $request->file('chunk');

            // Get upload info from session
            $uploadInfo = session("upload_{$uploadId}");
            if (!$uploadInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload session not found'
                ], 404);
            }

            // Save chunk
            $chunkPath = $uploadInfo['temp_dir'] . "/chunk_{$chunkIndex}";
            $chunk->move(dirname($chunkPath), basename($chunkPath));

            // Update session
            $uploadInfo['chunks_received'] = $chunkIndex + 1;
            session(["upload_{$uploadId}" => $uploadInfo]);

            Log::info("Chunk uploaded", [
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'chunks_received' => $uploadInfo['chunks_received']
            ]);

            return response()->json([
                'status' => 'success',
                'chunk_index' => $chunkIndex,
                'chunks_received' => $uploadInfo['chunks_received']
            ]);

        } catch (Exception $e) {
            Log::error('Upload chunk error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize chunked upload
     */
    public function finalizeChunkedUpload(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string',
            'total_chunks' => 'required|integer'
        ]);

        try {
            $uploadId = $request->upload_id;
            $totalChunks = $request->total_chunks;

            // Get upload info
            $uploadInfo = session("upload_{$uploadId}");
            if (!$uploadInfo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload session not found'
                ], 404);
            }

            // Combine chunks
            $finalPath = $uploadInfo['temp_dir'] . '/final_file';
            $finalFile = fopen($finalPath, 'wb');

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $uploadInfo['temp_dir'] . "/chunk_{$i}";
                if (!file_exists($chunkPath)) {
                    fclose($finalFile);
                    throw new Exception("Missing chunk: {$i}");
                }

                $chunkFile = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunkFile, $finalFile);
                fclose($chunkFile);
                unlink($chunkPath); // Delete chunk after combining
            }
            fclose($finalFile);

            // Upload to Google Drive
            $result = $this->googleDriveService->uploadFile(
                $finalPath, 
                $uploadInfo['file_name'], 
                $uploadInfo['mime_type']
            );

            if ($result['success']) {
                // Create database record
                $user = \App\Models\User::find($uploadInfo['user_id']);
                $userFile = $user->files()->create([
                    'disk' => 'google_drive',
                    'path' => null,
                    'original_name' => $uploadInfo['file_name'],
                    'mime_type' => $uploadInfo['mime_type'],
                    'size' => $uploadInfo['file_size'],
                    'status' => 'AVAILABLE',
                    'google_drive_file_id' => $result['file_id']
                ]);

                // Cleanup
                unlink($finalPath);
                rmdir($uploadInfo['temp_dir']);
                session()->forget("upload_{$uploadId}");

                return response()->json([
                    'status' => 'success',
                    'message' => 'Upload hoàn tất!',
                    'data' => [
                        'file_id' => $userFile->id,
                        'file_name' => $uploadInfo['file_name'],
                        'google_drive_id' => $result['file_id']
                    ]
                ]);
            } else {
                throw new Exception($result['error']);
            }

        } catch (Exception $e) {
            Log::error('Finalize upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get direct upload URL for Google Drive
     */
    public function getDirectUploadUrl(Request $request)
    {
        $request->validate([
            'fileName' => 'required|string',
            'fileSize' => 'required|integer',
            'mimeType' => 'required|string'
        ]);

        try {
            $user = auth()->user();
            
            // Check storage limit
            if (!$user->isAdmin()) {
                $currentUsage = $user->files()->sum('size');
                $activeSubscription = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
                    
                if (!$activeSubscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ để upload file.'
                    ], 403);
                }
                
                $storageLimit = $activeSubscription->servicePackage->storage_limit * 1024 * 1024 * 1024;
                
                if (($currentUsage + $request->fileSize) > $storageLimit) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vượt quá giới hạn lưu trữ.'
                    ], 403);
                }
            }

            // Generate resumable upload URL
            $uploadUrl = $this->googleDriveService->createResumableUploadUrl(
                $request->fileName,
                $request->mimeType,
                $request->fileSize
            );

            if ($uploadUrl['success']) {
                // Create pending database record
                $userFile = $user->files()->create([
                    'disk' => 'google_drive',
                    'path' => null,
                    'original_name' => $request->fileName,
                    'mime_type' => $request->mimeType,
                    'size' => $request->fileSize,
                    'status' => 'UPLOADING',
                    'google_drive_file_id' => null,
                    'upload_session_url' => $uploadUrl['upload_url']
                ]);

                return response()->json([
                    'status' => 'success',
                    'upload_url' => $uploadUrl['upload_url'],
                    'file_id' => $userFile->id,
                    'chunk_size' => 8 * 1024 * 1024 // 8MB chunks for direct upload
                ]);
            } else {
                throw new Exception($uploadUrl['error']);
            }

        } catch (Exception $e) {
            Log::error('Direct upload URL error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * TRUE DIRECT UPLOAD - Initialize upload session
     * Browser sẽ upload trực tiếp lên Google Drive
     */
    public function initDirectUpload(Request $request)
    {
        try {
            $request->validate([
                'fileName' => 'required|string',
                'fileSize' => 'required|integer|min:1',
                'mimeType' => 'required|string'
            ]);

            $user = auth()->user();
            $fileName = $request->fileName;
            $fileSize = $request->fileSize;
            $mimeType = $request->mimeType;

            // Check storage limit (Admin = unlimited)
            if (!$user->isAdmin()) {
                $subscription = $user->subscription;
                if (!$subscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Không có gói dịch vụ'
                    ]);
                }

                $usedStorage = $user->files()->sum('size');
                $storageLimit = $subscription->servicePackage->storage_limit * 1024 * 1024 * 1024; // GB to bytes

                if (($usedStorage + $fileSize) > $storageLimit) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vượt quá giới hạn lưu trữ'
                    ]);
                }
            }

            // Create database record first
            $userFile = UserFile::create([
                'user_id' => $user->id,
                'original_name' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'status' => 'UPLOADING',
                'google_drive_file_id' => null, // Will be set later
                'disk' => 'google_drive',
                'path' => null
            ]);

            // Get Google Drive resumable upload URL
            $uploadUrl = $this->googleDriveService->createResumableUploadSession($fileName, $mimeType);

            return response()->json([
                'status' => 'success',
                'file_id' => $userFile->id,
                'upload_url' => $uploadUrl,
                'message' => 'Upload session created'
            ]);

        } catch (\Exception $e) {
            Log::error('Direct upload init failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi tạo upload session: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Finalize direct upload - Update database with Google Drive file ID
     */
    public function finalizeDirectUpload(Request $request)
    {
        try {
            $request->validate([
                'file_id' => 'required|integer',
                'google_drive_file_id' => 'required|string'
            ]);

            $fileId = $request->file_id;
            $googleDriveFileId = $request->google_drive_file_id;

            // Find the file record
            $userFile = UserFile::where('id', $fileId)
                ->where('user_id', auth()->id())
                ->first();

            if (!$userFile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File không tồn tại'
                ]);
            }

            // Get file metadata from Google Drive
            $driveFile = $this->googleDriveService->getFileMetadata($googleDriveFileId);

            // Update database record
            $userFile->update([
                'google_drive_file_id' => $googleDriveFileId,
                'size' => $driveFile['size'] ?? $userFile->size,
                'status' => 'AVAILABLE',
                'web_view_link' => $driveFile['webViewLink'] ?? null,
                'download_link' => $driveFile['webContentLink'] ?? null
            ]);

            Log::info("Direct upload completed", [
                'file_id' => $fileId,
                'google_drive_file_id' => $googleDriveFileId,
                'file_name' => $userFile->original_name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Upload hoàn thành',
                'file' => $userFile
            ]);

        } catch (\Exception $e) {
            Log::error('Direct upload finalize failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hoàn thành upload: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get Google Drive access token for direct upload
     */
    public function getGoogleDriveToken()
    {
        try {
            // Use the existing GoogleDriveService to get access token
            $reflection = new \ReflectionClass($this->googleDriveService);
            $clientProperty = $reflection->getProperty('client');
            $clientProperty->setAccessible(true);
            $client = $clientProperty->getValue($this->googleDriveService);
            
            $accessToken = $client->getAccessToken();
            if (!$accessToken) {
                $client->fetchAccessTokenWithAssertion();
                $accessToken = $client->getAccessToken();
            }

            return response()->json([
                'access_token' => $accessToken['access_token'],
                'expires_in' => $accessToken['expires_in'] ?? 3600
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get Google Drive access token: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get access token'
            ], 500);
        }
    }

    /**
     * Get Google Drive folder ID for direct upload
     */
    public function getGoogleDriveFolder()
    {
        try {
            $folderId = config('services.google_drive.folder_id');
            
            if (!$folderId) {
                throw new Exception('Google Drive folder ID not configured');
            }

            return response()->json([
                'folder_id' => $folderId
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get Google Drive folder ID: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get folder ID'
            ], 500);
        }
    }

    /**
     * Delete file (fallback route)
     */
    public function deleteFile(Request $request)
    {
        try {
            $request->validate([
                'file_id' => 'required|integer'
            ]);

            $user = auth()->user();
            $file = $user->files()->find($request->file_id);
            
            if (!$file) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File không tồn tại.'
                ], 404);
            }
            
            $fileName = $file->original_name;
            
            // Delete from Google Drive if exists
            if ($file->google_drive_file_id) {
                try {
                    $result = $this->googleDriveService->deleteFile($file->google_drive_file_id);
                    if (!$result['success']) {
                        Log::warning('Failed to delete file from Google Drive: ' . ($result['error'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete file from Google Drive: ' . $e->getMessage());
                }
            }
            
            // Delete from local storage if exists
            if ($file->path && Storage::exists($file->path)) {
                Storage::delete($file->path);
            }
            
            // Delete database record
            $file->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => "File '{$fileName}' đã được xóa thành công!"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Delete file error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi xóa file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * STREAMING PROXY UPLOAD - Tối ưu nhất!
     * File stream trực tiếp từ browser -> server -> Google Drive
     * Không lưu file tạm, không double upload
     */
    public function streamProxyUpload(Request $request)
    {
        // Set unlimited time for large files
        set_time_limit(0);
        ini_set('memory_limit', '256M'); // Chỉ cần buffer nhỏ
        
        Log::info('Streaming proxy upload started', ['user_id' => auth()->id()]);
        
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated.'], 401);
            }

            // Get file info from headers (sent by JavaScript)
            $fileName = $request->header('X-File-Name');
            $fileSize = (int) $request->header('X-File-Size');
            $mimeType = $request->header('X-File-Type');
            
            if (!$fileName || !$fileSize || !$mimeType) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Missing file information in headers'
                ], 400);
            }

            // Validate file type
            if (!in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chỉ hỗ trợ file video (mp4, mov, avi, mkv)'
                ], 400);
            }
            
            // Check storage limit for non-admin users
            if (!$user->isAdmin()) {
                $currentUsage = $user->files()->sum('size');
                $activeSubscription = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
                    
                if (!$activeSubscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ để upload file.'
                    ], 403);
                }
                
                $storageLimit = $activeSubscription->servicePackage->storage_limit * 1024 * 1024 * 1024;
                
                if (($currentUsage + $fileSize) > $storageLimit) {
                    $remaining = $storageLimit - $currentUsage;
                    $remainingGB = round($remaining / (1024 * 1024 * 1024), 2);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Vượt quá giới hạn lưu trữ. Dung lượng còn lại: {$remainingGB}GB"
                    ], 403);
                }
            }

            // Get input stream (raw request body)
            $inputStream = fopen('php://input', 'rb');
            if (!$inputStream) {
                throw new Exception('Cannot read input stream');
            }

            Log::info('Starting streaming proxy to Google Drive', [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ]);

            // Stream directly to Google Drive
            $result = $this->googleDriveService->streamUploadFile($inputStream, $fileName, $mimeType, $fileSize);
            
            fclose($inputStream);
            
            Log::info('Google Drive streaming result', ['success' => $result['success']]);

            if ($result['success']) {
                // Create database record
                $userFile = $user->files()->create([
                    'disk' => 'google_drive',
                    'path' => null,
                    'original_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'status' => 'AVAILABLE',
                    'google_drive_file_id' => $result['file_id']
                ]);

                Log::info('Database record created', ['user_file_id' => $userFile->id]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Upload thành công!',
                    'data' => [
                        'file_id' => $userFile->id,
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'google_drive_id' => $result['file_id']
                    ]
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Unknown error during Google Drive streaming.');
            }

        } catch (Exception $e) {
            Log::error('Streaming proxy upload error', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi streaming upload: ' . $e->getMessage(),
            ], 500);
        }
    }
}
