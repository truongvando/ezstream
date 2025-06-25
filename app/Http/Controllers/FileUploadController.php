<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleDriveService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Handle file upload for authenticated user.
     */
    public function upload(Request $request)
    {
        // Set reasonable time limit for large uploads
        set_time_limit(600); // 10 minutes
        
        Log::info('FileManager upload started', ['user_id' => auth()->id()]);
        
        $request->validate([
            'file' => 'required|file|mimes:mp4,mov,avi,mkv' // Video files only
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
                    
                if (!$activeSubscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Bạn cần có gói dịch vụ để upload file.'
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
                'file_size' => $uploadedFile->getSize(),
                'temp_path' => $tempPath,
            ]);

            // Upload to Google Drive
            $result = $this->googleDriveService->uploadFile($tempPath, $fileName, $uploadedFile->getMimeType());
            
            Log::info('Google Drive upload result', ['success' => $result['success']]);

            if ($result['success']) {
                // Create database record
                $userFile = $user->files()->create([
                    'disk' => 'google_drive',
                    'path' => null,
                    'original_name' => $fileName,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $uploadedFile->getSize(),
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
                        'file_size' => $uploadedFile->getSize(),
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
     * Finalize direct upload
     */
    public function finalizeDirectUpload(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'google_drive_file_id' => 'required|string'
        ]);

        try {
            $user = auth()->user();
            $userFile = $user->files()->find($request->file_id);
            
            if (!$userFile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File record not found'
                ], 404);
            }

            // Update file record
            $userFile->update([
                'status' => 'AVAILABLE',
                'google_drive_file_id' => $request->google_drive_file_id,
                'upload_session_url' => null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Upload finalized successfully',
                'data' => [
                    'file_id' => $userFile->id,
                    'file_name' => $userFile->original_name,
                    'google_drive_id' => $request->google_drive_file_id
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Finalize direct upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
