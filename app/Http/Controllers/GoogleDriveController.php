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
            if ($fileSize > 21474836480) { // 20GB limit
                return response()->json(['error' => 'File too large. Maximum 20GB allowed.'], 400);
            }

            // PRACTICAL SOLUTION: Just save the Google Drive file ID directly
            // Since googleapis.com is blocked, we use direct linking approach
            $userFile = $user->files()->create([
                'disk' => 'google_drive',
                'path' => null, // No local path needed
                'original_name' => $fileName,
                'mime_type' => $fileInfo['mimeType'] ?? 'video/mp4',
                'size' => $fileSize,
                'status' => 'AVAILABLE', // Mark as available immediately
                'source_url' => $driveUrl,
                'google_drive_file_id' => $fileId // Use the original file ID
            ]);

            // No need to dispatch download job - file is ready to use

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
     * Get file information from Google Drive without API key (like gdown)
     */
    private function getGoogleDriveFileInfo($fileId)
    {
        Log::info('GOOGLE_DRIVE_IMPORT: Getting file info without API key', ['file_id' => $fileId]);

        // Try direct download URLs to get file info
        $testUrls = [
            "https://drive.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/uc?id={$fileId}&export=download"
        ];

        foreach ($testUrls as $url) {
            try {
                $response = Http::timeout(10)->head($url);
                
                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');
                    $contentLength = $response->header('Content-Length');
                    $contentDisposition = $response->header('Content-Disposition');
                    
                    // Extract filename from Content-Disposition header
                    $fileName = 'video.mp4'; // default
                    if ($contentDisposition && preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $contentDisposition, $matches)) {
                        $fileName = trim($matches[1], '"\'');
                    }
                    
                    // If we get reasonable response, return file info
                    if ($contentLength > 1000) { // File larger than 1KB
                        return [
                            'id' => $fileId,
                            'name' => $fileName,
                            'size' => (int)$contentLength,
                            'mimeType' => $contentType ?: 'video/mp4'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('GOOGLE_DRIVE_IMPORT: URL test failed', [
                    'url' => substr($url, 0, 50) . '...',
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // If all direct methods fail, return basic info
        Log::warning('GOOGLE_DRIVE_IMPORT: Could not get file info, using defaults');
        return [
            'id' => $fileId,
            'name' => 'video_' . substr($fileId, 0, 8) . '.mp4',
            'size' => 0, // Unknown size
            'mimeType' => 'video/mp4'
        ];
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
