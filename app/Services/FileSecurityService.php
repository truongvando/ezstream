<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class FileSecurityService
{
    /**
     * Validate if the uploaded file is a legitimate video file based on extension and MIME type.
     * This is a simplified, more practical approach for a video streaming platform.
     *
     * @param UploadedFile $file The uploaded file instance.
     * @param string $originalName The original name of the file.
     * @return array
     */
    public function validateUploadedVideo(UploadedFile $file, string $originalName): array
    {
        if (!$file->isValid()) {
            return ['valid' => false, 'reason' => 'Uploaded file is not valid. Error code: ' . $file->getError()];
        }

        // 1. Validate by file extension
        if (!$this->isAllowedExtension($originalName)) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            return ['valid' => false, 'reason' => "File extension '{$extension}' is not allowed."];
        }

        // 2. Validate by MIME type
        $mimeType = $file->getMimeType();
        if (!$this->isAllowedMimeType($mimeType)) {
            return ['valid' => false, 'reason' => "File MIME type '{$mimeType}' is not allowed."];
        }

        // 3. Basic file size check
        if ($file->getSize() < 1024) { // Less than 1KB is suspicious for a video
            return ['valid' => false, 'reason' => 'File size is too small to be a video.'];
        }
        
        // All checks passed
        return ['valid' => true, 'reason' => 'Valid video file'];
    }

    /**
     * Validate a local video file that is already on the server's disk.
     * This is used for files downloaded from external sources like Google Drive.
     *
     * @param string $filePath The absolute path to the local file.
     * @param string $originalName The original name of the file to check extension.
     * @return array
     */
    public function validateLocalVideoFile(string $filePath, string $originalName): array
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'reason' => 'Local file not found for validation.'];
        }

        // 1. Validate by file extension from original name
        if (!$this->isAllowedExtension($originalName)) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            return ['valid' => false, 'reason' => "File extension '{$extension}' is not allowed."];
        }

        // 2. Basic file size check
        if (filesize($filePath) < 1024) { // Less than 1KB
            return ['valid' => false, 'reason' => 'File size is too small to be a video.'];
        }

        // 3. Check if file contains HTML content (common issue with Google Drive downloads)
        $fileHeader = file_get_contents($filePath, false, null, 0, 1024);
        
        // More specific HTML detection for Google Drive errors
        if (str_contains($fileHeader, '<!DOCTYPE html') || 
            str_contains($fileHeader, '<html lang=') ||
            str_contains($fileHeader, 'Google Drive - Virus scan warning') ||
            str_contains($fileHeader, 'accounts.google.com/signin')) {
            return ['valid' => false, 'reason' => 'File appears to be HTML error page from Google Drive'];
        }

        // 4. For video files, check for basic video file signatures
        $videoSignatures = [
            'ftyp', // MP4 signature
            'RIFF', // AVI signature
            'matroska', // MKV signature
            'moov', // MOV signature
        ];
        
        $headerText = substr($fileHeader, 0, 100);
        $hasVideoSignature = false;
        foreach ($videoSignatures as $signature) {
            if (str_contains($headerText, $signature)) {
                $hasVideoSignature = true;
                break;
            }
        }

        // 5. Validate by MIME type detected from the local file (more lenient for downloads)
        $mimeType = mime_content_type($filePath);
        if (!$this->isAllowedMimeType($mimeType)) {
            // For downloaded files, be more lenient with MIME type detection
            // If extension is valid, file doesn't contain HTML, and has video signature, allow it
            if ($hasVideoSignature) {
                Log::info('MIME type validation bypassed for downloaded file with video signature', [
                    'file_path' => basename($filePath),
                    'detected_mime' => $mimeType,
                    'original_name' => $originalName,
                    'has_video_signature' => $hasVideoSignature
                ]);
            } else {
                Log::warning('File rejected: No video signature found and invalid MIME type', [
                    'file_path' => basename($filePath),
                    'detected_mime' => $mimeType,
                    'original_name' => $originalName,
                    'header_hex' => bin2hex(substr($fileHeader, 0, 50))
                ]);
                return ['valid' => false, 'reason' => "File does not appear to be a valid video file (MIME: {$mimeType})"];
            }
        }
        
        // All checks passed
        return ['valid' => true, 'reason' => 'Valid local video file'];
    }

    /**
     * Sanitize filename
     */
    public function sanitizeFileName(string $filename): ?string
    {
        // Remove potentially dangerous characters, but allow more flexibility for different languages
        $filename = preg_replace('/[\\/\\?%\\*:\\|"<>]/', '_', $filename);
        
        // Prevent path traversal
        $filename = str_replace('..', '', $filename);
        
        // Remove multiple dots and underscores to clean up
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        
        // Check for dangerous reserved filenames (Windows/Linux)
        $dangerousNames = [
            'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 
            'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 
            'lpt6', 'lpt7', 'lpt8', 'lpt9', '.htaccess', 'web.config', 'index.php'
        ];
        
        $nameWithoutExt = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        if (in_array($nameWithoutExt, $dangerousNames)) {
            Log::warning('Dangerous filename detected and sanitized.', ['original' => $filename]);
            return 'renamed_' . $filename;
        }
        
        // Check length
        if (strlen($filename) > 255 || strlen($filename) < 1) {
            return null;
        }
        
        return $filename;
    }

    /**
     * Check if file extension is allowed
     */
    public function isAllowedExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv'];
        
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Check if MIME type is allowed
     */
    public function isAllowedMimeType(?string $mimeType): bool
    {
        if (!$mimeType) return false;

        $allowedMimes = [
            'video/mp4', 
            'video/quicktime', 
            'video/x-msvideo', 
            'video/x-matroska',
            'video/webm',
            'video/x-flv',
        ];
        
        // Also allow generic binary streams as they can sometimes be video files
        // that the system couldn't properly identify. The extension check is the primary guard.
        if (in_array($mimeType, ['application/octet-stream', 'binary/octet-stream'])) {
            return true;
        }

        return in_array($mimeType, $allowedMimes);
    }
} 