<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Http\MediaFileUpload;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleDriveService
{
    protected $client;
    protected $service;
    protected $folderId;

    public function __construct()
    {
        $this->initializeClient();
        $this->folderId = config('services.google_drive.folder_id');
    }

    /**
     * Initialize Google Client
     */
    private function initializeClient()
    {
        try {
            $this->client = new Client();
            
            // Set service account credentials
            $serviceAccountPath = storage_path('app/credentials/google-service-account.json');
            
            if (!file_exists($serviceAccountPath)) {
                throw new Exception("Service account file not found at: {$serviceAccountPath}");
            }

            $this->client->setAuthConfig($serviceAccountPath);
            $this->client->addScope(Drive::DRIVE);
            $this->client->setApplicationName('VPS Live Server Control');

            $this->service = new Drive($this->client);
            
            Log::info('Google Drive service initialized successfully');
        } catch (Exception $e) {
            Log::error('Failed to initialize Google Drive service: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload file to Google Drive
     */
    public function uploadFile($filePath, $fileName, $mimeType = null)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            $fileSize = filesize($filePath);

            // Auto-detect MIME type if not provided
            if (!$mimeType) {
                $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            }

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$this->folderId]
            ]);

            // Use resumable upload for files larger than 5MB
            if ($fileSize > 5 * 1024 * 1024) {
                Log::info("Starting resumable upload for large file.", ['file_size' => $fileSize]);
                return $this->uploadLargeFileResumable($filePath, $fileMetadata, $mimeType, $fileSize);
            }

            // Use multipart for smaller files
            Log::info("Starting multipart upload for small file.", ['file_size' => $fileSize]);
            $content = file_get_contents($filePath);
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,size,webViewLink,webContentLink'
            ]);

            Log::info("File uploaded to Google Drive", [
                'file_id' => $file->getId(),
                'file_name' => $file->getName(),
                'file_size' => $file->getSize()
            ]);

            return [
                'success' => true,
                'file_id' => $file->getId(),
                'file_name' => $file->getName(),
                'file_size' => $file->getSize(),
                'web_view_link' => $file->getWebViewLink(),
                'download_link' => $file->getWebContentLink()
            ];

        } catch (Exception $e) {
            Log::error('Failed to upload file to Google Drive: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload large file using resumable upload
     */
    private function uploadLargeFileResumable($filePath, $fileMetadata, $mimeType, $fileSize)
    {
        try {
            $chunkSizeBytes = 8 * 1024 * 1024; // 8MB chunks
            $this->client->setDefer(true);
            $request = $this->service->files->create($fileMetadata);

            $media = new MediaFileUpload(
                $this->client,
                $request,
                $mimeType,
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize($fileSize);

            // Upload in chunks
            $status = false;
            $handle = fopen($filePath, "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);
            $this->client->setDefer(false);

            if ($status) {
                $file = $status;
                Log::info("Large file uploaded to Google Drive", [
                    'file_id' => $file->getId(),
                    'file_name' => $file->getName(),
                ]);
                return [
                    'success' => true,
                    'file_id' => $file->getId(),
                    'file_name' => $file->getName(),
                    'file_size' => $file->getSize(),
                    'web_view_link' => $file->getWebViewLink(),
                    'download_link' => $file->getWebContentLink()
                ];
            } else {
                throw new Exception('Resumable upload failed to complete.');
            }
        } catch (Exception $e) {
            $this->client->setDefer(false); // Make sure to reset client state on error
            Log::error('Failed to upload large file: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Download file from Google Drive
     */
    public function downloadFile($fileId, $savePath)
    {
        try {
            $response = $this->service->files->get($fileId, ['alt' => 'media']);
            $content = $response->getBody()->getContents();

            // Ensure directory exists
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($savePath, $content);

            Log::info("File downloaded from Google Drive", [
                'file_id' => $fileId,
                'save_path' => $savePath,
                'file_size' => strlen($content)
            ]);

            return [
                'success' => true,
                'file_path' => $savePath,
                'file_size' => strlen($content)
            ];

        } catch (Exception $e) {
            Log::error('Failed to download file from Google Drive: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List files in Google Drive folder
     */
    public function listFiles($pageSize = 10, $pageToken = null)
    {
        try {
            $optParams = [
                'q' => "'{$this->folderId}' in parents and trashed=false",
                'pageSize' => $pageSize,
                'fields' => 'nextPageToken, files(id, name, size, mimeType, createdTime, modifiedTime, webViewLink)',
                'orderBy' => 'modifiedTime desc'
            ];

            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }

            $results = $this->service->files->listFiles($optParams);
            $files = $results->getFiles();

            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'created_time' => $file->getCreatedTime(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink()
                ];
            }

            return [
                'success' => true,
                'files' => $fileList,
                'next_page_token' => $results->getNextPageToken()
            ];

        } catch (Exception $e) {
            Log::error('Failed to list files from Google Drive: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete file from Google Drive
     */
    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId);

            Log::info("File deleted from Google Drive", ['file_id' => $fileId]);

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete file from Google Drive: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file info from Google Drive
     */
    public function getFileInfo($fileId)
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, size, mimeType, createdTime, modifiedTime, webViewLink, webContentLink'
            ]);

            return [
                'success' => true,
                'file' => [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'created_time' => $file->getCreatedTime(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                    'download_link' => $file->getWebContentLink()
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to get file info from Google Drive: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get direct download link for streaming
     */
    public function getDirectDownloadLink($fileId)
    {
        try {
            // Get file info first
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, size, webContentLink'
            ]);

            $downloadLink = $file->getWebContentLink();
            
            // If webContentLink is not available, create direct download URL
            if (!$downloadLink) {
                $downloadLink = "https://drive.google.com/uc?export=download&id={$fileId}";
            }

            Log::info("Generated direct download link", [
                'file_id' => $fileId,
                'file_name' => $file->getName(),
                'download_link' => $downloadLink
            ]);

            return [
                'success' => true,
                'download_link' => $downloadLink,
                'file_name' => $file->getName(),
                'file_size' => $file->getSize()
            ];

        } catch (Exception $e) {
            Log::error('Failed to get direct download link: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if service is properly configured
     */
    public function testConnection()
    {
        try {
            // Try to get folder info
            $folder = $this->service->files->get($this->folderId, [
                'fields' => 'id, name, mimeType'
            ]);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'folder_name' => $folder->getName(),
                'folder_id' => $folder->getId()
            ];

        } catch (Exception $e) {
            Log::error('Google Drive connection test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create resumable upload URL for direct upload from browser
     */
    public function createResumableUploadUrl($fileName, $mimeType, $fileSize)
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$this->folderId]
            ]);

            // Set up resumable upload
            $this->client->setDefer(true);
            $request = $this->service->files->create($fileMetadata);
            
            // Configure resumable upload
            $media = new MediaFileUpload(
                $this->client,
                $request,
                $mimeType,
                null,
                true,
                8 * 1024 * 1024 // 8MB chunks
            );
            $media->setFileSize($fileSize);

            // Get the upload URL
            $uploadUrl = $media->getResumeUri();
            $this->client->setDefer(false);

            Log::info("Created resumable upload URL", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'upload_url' => substr($uploadUrl, 0, 50) . '...'
            ]);

            return [
                'success' => true,
                'upload_url' => $uploadUrl,
                'chunk_size' => 8 * 1024 * 1024
            ];

        } catch (Exception $e) {
            $this->client->setDefer(false);
            Log::error('Failed to create resumable upload URL: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 