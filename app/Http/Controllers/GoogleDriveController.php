<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\UserFile;
use App\Jobs\DownloadFromGoogleDriveJob;
use App\Services\FileSecurityService;

class GoogleDriveController extends Controller
{
    private FileSecurityService $fileSecurityService;

    public function __construct(FileSecurityService $fileSecurityService)
    {
        $this->fileSecurityService = $fileSecurityService;
    }

    /**
     * Initialize Google Drive download
     */
    public function initDownload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driveUrl' => 'required|url|max:500',
            'fileName' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters', 'details' => $validator->errors()], 400);
        }

        $user = Auth::user();
        $driveUrl = $request->driveUrl;

        // Extract Google Drive file ID
        $fileId = $this->extractGoogleDriveFileId($driveUrl);
        if (!$fileId) {
            Log::warning('Google Drive download failed - invalid URL', [
                'user_id' => $user->id,
                'url' => $driveUrl,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Invalid Google Drive URL'], 400);
        }

        // Rate limiting - max 10 downloads per hour per user
        $downloadKey = 'gdrive_downloads_' . $user->id;
        $downloads = cache()->get($downloadKey, 0);
        if ($downloads >= 10) {
            return response()->json(['error' => 'Download limit exceeded. Max 10 downloads per hour.'], 429);
        }

        try {
            // Get file info from Google Drive
            $fileInfo = $this->getGoogleDriveFileInfo($fileId);
            if (!$fileInfo) {
                return response()->json(['error' => 'Cannot access file. Make sure the file is publicly accessible.'], 400);
            }

            // Validate file
            $fileName = $request->fileName ?: $fileInfo['name'];
            $fileName = $this->fileSecurityService->sanitizeFileName($fileName);
            
            if (!$fileName || !$this->fileSecurityService->isAllowedExtension($fileName)) {
                return response()->json(['error' => 'Invalid file type. Only MP4, MOV, AVI, MKV are allowed.'], 400);
            }

            // Check file size
            $fileSize = $fileInfo['size'] ?? 0;
            if ($fileSize > 107374182400) { // 100GB limit
                return response()->json(['error' => 'File too large. Maximum 100GB allowed.'], 400);
            }

            // No storage limits for video streaming system

            // Create database record
            $userFile = $user->files()->create([
                'disk' => 'local',
                'path' => 'pending_download/' . uniqid() . '_' . $fileName,
                'original_name' => $fileName,
                'mime_type' => $fileInfo['mimeType'] ?? 'video/mp4',
                'size' => $fileSize,
                'status' => 'DOWNLOADING',
                'source_url' => $driveUrl,
                'google_drive_file_id' => $fileId
            ]);

            // Dispatch download job
            DownloadFromGoogleDriveJob::dispatch($userFile, $fileId);

            // Increment download counter
            cache()->put($downloadKey, $downloads + 1, 3600);

            Log::info('Google Drive download initiated', [
                'user_id' => $user->id,
                'file_id' => $userFile->id,
                'drive_file_id' => $fileId,
                'filename' => $fileName,
                'size' => $fileSize,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'fileId' => $userFile->id,
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'status' => 'downloading',
                'message' => 'Download started successfully. You will be notified when complete.'
            ]);

        } catch (\Exception $e) {
            Log::error('Google Drive download error', [
                'user_id' => $user->id,
                'url' => $driveUrl,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Failed to process download request'], 500);
        }
    }

    /**
     * Check download progress
     */
    public function checkProgress($fileId)
    {
        $user = Auth::user();
        $userFile = $user->files()->find($fileId);

        if (!$userFile) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->json([
            'fileId' => $userFile->id,
            'fileName' => $userFile->original_name,
            'status' => $userFile->status,
            'size' => $userFile->size,
            'progress' => $this->getDownloadProgress($userFile)
        ]);
    }

    /**
     * Extract Google Drive file ID from various URL formats
     */
    private function extractGoogleDriveFileId($url)
    {
        // Remove any tracking parameters and clean URL
        $url = strtok($url, '?');
        
        $patterns = [
            // https://drive.google.com/file/d/FILE_ID/view
            '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/',
            // https://drive.google.com/open?id=FILE_ID
            '/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/',
            // https://docs.google.com/document/d/FILE_ID/
            '/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/',
            // Direct file ID (if user provides just the ID)
            '/^([a-zA-Z0-9_-]{25,})$/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        // Try to extract from URL parameters
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);
            if (isset($params['id'])) {
                return $params['id'];
            }
        }

        return null;
    }

    /**
     * Get file information from Google Drive API
     */
    private function getGoogleDriveFileInfo($fileId)
    {
        try {
            // Use Google Drive API v3 (public access)
            $response = Http::timeout(30)->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'fields' => 'id,name,size,mimeType,webViewLink',
                'key' => config('services.google.api_key') // You'll need to add this
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            // Fallback: Try to get basic info from public link
            $publicUrl = "https://drive.google.com/file/d/{$fileId}/view";
            $response = Http::timeout(15)->get($publicUrl);
            
            if ($response->successful()) {
                $html = $response->body();
                
                // Extract filename from HTML
                if (preg_match('/<title>(.+?) - Google Drive<\/title>/', $html, $matches)) {
                    return [
                        'id' => $fileId,
                        'name' => trim($matches[1]),
                        'size' => 0, // Unknown size
                        'mimeType' => 'video/mp4'
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get Google Drive file info', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get download progress
     */
    private function getDownloadProgress($userFile)
    {
        $progressKey = 'download_progress_' . $userFile->id;
        return cache()->get($progressKey, 0);
    }

    /**
     * Validate Google Drive URL format
     */
    public function validateUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false, 'error' => 'Invalid URL format']);
        }

        $fileId = $this->extractGoogleDriveFileId($request->url);
        if (!$fileId) {
            return response()->json(['valid' => false, 'error' => 'Not a valid Google Drive URL']);
        }

        $fileInfo = $this->getGoogleDriveFileInfo($fileId);
        if (!$fileInfo) {
            return response()->json(['valid' => false, 'error' => 'Cannot access file. Make sure it\'s publicly accessible.']);
        }

        return response()->json([
            'valid' => true,
            'fileId' => $fileId,
            'fileName' => $fileInfo['name'] ?? 'Unknown',
            'fileSize' => $fileInfo['size'] ?? 0,
            'mimeType' => $fileInfo['mimeType'] ?? 'unknown'
        ]);
    }
}
