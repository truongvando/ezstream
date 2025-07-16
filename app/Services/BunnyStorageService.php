<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class BunnyStorageService
{
    protected $storageZone;
    protected $accessKey;
    protected $readOnlyPassword;
    protected $baseUrl;
    protected $cdnUrl;

    public function __construct()
    {
        $this->storageZone = config('services.bunny.storage_zone', 'ezstream');
        $this->accessKey = config('services.bunny.access_key');
        $this->readOnlyPassword = config('services.bunny.read_only_password');
        $this->baseUrl = "https://sg.storage.bunnycdn.com/{$this->storageZone}";
        $this->cdnUrl = config('services.bunny.cdn_url', "https://ezstream.b-cdn.net");
    }

    /**
     * Upload file to Bunny.net Storage
     */
    public function uploadFile($source, $fileName, $mimeType = null)
    {
        try {
            $isUploadedFile = $source instanceof \Illuminate\Http\UploadedFile;
            $fileSize = $isUploadedFile ? $source->getSize() : filesize($source);
            $filePath = $isUploadedFile ? $source->getRealPath() : $source;

            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Generate unique path
            $userId = auth()->id();
            $datePrefix = date('Y/m/d');
            $uniqueFileName = time() . '_' . $userId . '_' . $fileName;
            $remotePath = "users/{$userId}/{$datePrefix}/{$uniqueFileName}";

            Log::info("Starting Bunny.net upload", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'remote_path' => $remotePath
            ]);

            // Use chunked upload for files larger than 50MB
            if ($fileSize > 50 * 1024 * 1024) {
                return $this->uploadFileChunked($filePath, $remotePath, $fileName, $fileSize, $mimeType);
            }

            // Read file content for small files
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                throw new Exception("Cannot read file content");
            }

            // Upload to Bunny.net via HTTP API with extended timeout
            $response = Http::timeout(600) // 10 minutes timeout
                ->withHeaders([
                    'AccessKey' => $this->accessKey,
                    'Content-Type' => $mimeType ?: 'application/octet-stream'
                ])->withBody($fileContent)
                ->put("{$this->baseUrl}/{$remotePath}");

            if ($response->failed()) {
                throw new Exception("Bunny.net upload failed: " . $response->body());
            }

            $cdnUrl = "{$this->cdnUrl}/{$remotePath}";

            Log::info("Bunny.net upload completed", [
                'remote_path' => $remotePath,
                'cdn_url' => $cdnUrl,
                'response_status' => $response->status()
            ]);

            return [
                'success' => true,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'remote_path' => $remotePath,
                'cdn_url' => $cdnUrl,
                'storage_type' => 'bunny_cdn'
            ];

        } catch (Exception $e) {
            Log::error('Bunny.net upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Stream upload file to Bunny.net
     */
    public function streamUploadFile($inputStream, $fileName, $mimeType, $fileSize)
    {
        try {
            $userId = auth()->id();
            $datePrefix = date('Y/m/d');
            $uniqueFileName = time() . '_' . $userId . '_' . $fileName;
            $remotePath = "users/{$userId}/{$datePrefix}/{$uniqueFileName}";

            Log::info("Starting Bunny.net stream upload", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'remote_path' => $remotePath
            ]);

            // For large files, we need to read the stream in chunks
            $fileContent = '';
            while (!feof($inputStream)) {
                $chunk = fread($inputStream, 8192); // 8KB chunks
                if ($chunk === false) break;
                $fileContent .= $chunk;
            }

            // Upload to Bunny.net
            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey,
                'Content-Type' => $mimeType,
                'Content-Length' => strlen($fileContent)
            ])->withBody($fileContent)
            ->put("{$this->baseUrl}/{$remotePath}");

            if ($response->failed()) {
                throw new Exception("Bunny.net stream upload failed: " . $response->body());
            }

            $cdnUrl = "{$this->cdnUrl}/{$remotePath}";

            return [
                'success' => true,
                'file_name' => $fileName,
                'file_size' => strlen($fileContent),
                'remote_path' => $remotePath,
                'cdn_url' => $cdnUrl,
                'storage_type' => 'bunny_cdn'
            ];

        } catch (Exception $e) {
            Log::error('Bunny.net stream upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get direct download URL (CDN URL)
     */
    public function getDirectDownloadLink($remotePath)
    {
        // URL encode the path to handle spaces and special characters
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $remotePath)));
        $cdnUrl = "{$this->cdnUrl}/{$encodedPath}";

        return [
            'success' => true,
            'download_link' => $cdnUrl,
            'cdn_url' => $cdnUrl
        ];
    }

    /**
     * Delete file from Bunny.net
     */
    public function deleteFile($remotePath)
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey
            ])->delete("{$this->baseUrl}/{$remotePath}");

            if ($response->failed() && $response->status() !== 404) {
                throw new Exception("Failed to delete file: " . $response->body());
            }

            Log::info("File deleted from Bunny.net", ['remote_path' => $remotePath]);

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete file from Bunny.net: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to Bunny.net
     */
    public function testConnection()
    {
        try {
            // Test by listing root directory
            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey
            ])->get("{$this->baseUrl}/");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Bunny.net connection successful',
                    'storage_zone' => $this->storageZone,
                    'cdn_url' => $this->cdnUrl
                ];
            } else {
                throw new Exception("Connection failed: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error('Bunny.net connection test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file info from Bunny.net
     */
    public function getFileInfo($remotePath)
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->readOnlyPassword // Use read-only for info
            ])->get("{$this->baseUrl}/{$remotePath}");

            if ($response->successful()) {
                $headers = $response->headers();
                
                return [
                    'success' => true,
                    'file_size' => $headers['Content-Length'][0] ?? 0,
                    'content_type' => $headers['Content-Type'][0] ?? 'application/octet-stream',
                    'last_modified' => $headers['Last-Modified'][0] ?? null,
                    'cdn_url' => "{$this->cdnUrl}/{$remotePath}"
                ];
            } else {
                throw new Exception("File not found or access denied");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Download file from Bunny.net to local path
     */
    public function downloadToLocal($remotePath, $localPath)
    {
        try {
            $cdnUrl = "{$this->cdnUrl}/{$remotePath}";
            
            Log::info("Downloading from Bunny.net to local", [
                'cdn_url' => $cdnUrl,
                'local_path' => $localPath
            ]);

            // Download via CDN (faster than storage API)
            $response = Http::timeout(300)->get($cdnUrl); // 5 minute timeout

            if ($response->failed()) {
                throw new Exception("Failed to download from CDN: " . $response->status());
            }

            // Ensure directory exists
            $directory = dirname($localPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Write to local file
            $bytesWritten = file_put_contents($localPath, $response->body());
            
            if ($bytesWritten === false) {
                throw new Exception("Failed to write to local file");
            }

            Log::info("Download completed", [
                'bytes_written' => $bytesWritten,
                'local_path' => $localPath
            ]);

            return [
                'success' => true,
                'local_path' => $localPath,
                'file_size' => $bytesWritten
            ];

        } catch (Exception $e) {
            Log::error('Download from Bunny.net failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload large file using stream approach (Bunny.net doesn't support chunked uploads)
     */
    private function uploadFileChunked($filePath, $remotePath, $fileName, $fileSize, $mimeType)
    {
        Log::info("Starting large file upload to Bunny.net", [
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'remote_path' => $remotePath
        ]);

        // For Bunny.net, we need to upload the entire file at once
        // But we'll use a file stream to avoid loading everything into memory

        try {
            $fileStream = fopen($filePath, 'rb');
            if (!$fileStream) {
                throw new Exception("Cannot open file for reading");
            }

            // Upload with extended timeout and retry logic
            $response = Http::timeout(1800) // 30 minutes timeout
                ->withHeaders([
                    'AccessKey' => $this->accessKey,
                    'Content-Type' => $mimeType ?: 'application/octet-stream',
                    'Content-Length' => $fileSize
                ])
                ->withBody($fileStream)
                ->put("{$this->baseUrl}/{$remotePath}");

            fclose($fileStream);

            if ($response->failed()) {
                throw new Exception("Bunny.net upload failed: " . $response->body());
            }

            $cdnUrl = "{$this->cdnUrl}/{$remotePath}";

            Log::info("Large file upload completed", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'cdn_url' => $cdnUrl,
                'response_status' => $response->status()
            ]);

            return [
                'success' => true,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'remote_path' => $remotePath,
                'cdn_url' => $cdnUrl,
                'storage_type' => 'bunny_cdn'
            ];

        } catch (Exception $e) {
            Log::error('Large file upload to Bunny.net failed: ' . $e->getMessage());
            throw $e;
        }
    }



    /**
     * List files in a directory from Bunny.net
     */
    public function listFiles($directory = '')
    {
        try {
            $url = "{$this->baseUrl}/{$directory}";

            $response = Http::withHeaders([
                'AccessKey' => $this->accessKey
            ])->get($url);

            if ($response->failed()) {
                throw new Exception("Failed to list files: " . $response->body());
            }

            $files = $response->json();

            return [
                'success' => true,
                'files' => $files
            ];

        } catch (Exception $e) {
            Log::error('Failed to list Bunny.net files: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List files for a specific user
     */
    public function listUserFiles($userId)
    {
        return $this->listFiles("users/{$userId}");
    }



    /**
     * Get storage statistics
     */
    public function getStorageStats()
    {
        // Bunny.net doesn't provide detailed API for storage stats
        // This would need to be tracked in database
        return [
            'storage_zone' => $this->storageZone,
            'cdn_url' => $this->cdnUrl,
            'note' => 'Storage stats tracked in database'
        ];
    }
}
