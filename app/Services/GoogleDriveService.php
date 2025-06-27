<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Http\MediaFileUpload;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Http;

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
            
            // Try OAuth first (user account with storage), fallback to Service Account
            $refreshToken = config('services.google_drive.refresh_token');
            
            if ($refreshToken) {
                // Use OAuth User Account (has storage quota)
                Log::info('Using OAuth User Account for Google Drive');
                
                $this->client->setClientId(config('services.google_drive.client_id'));
                $this->client->setClientSecret(config('services.google_drive.client_secret'));
                $this->client->setAccessType('offline');
                $this->client->addScope(Drive::DRIVE);
                $this->client->setApplicationName('VPS Live Server Control');
                
                // Set API key if available
                $apiKey = config('services.google.api_key');
                if ($apiKey) {
                    $this->client->setDeveloperKey($apiKey);
                }
                
                // Set refresh token
                $this->client->refreshToken($refreshToken);
                
            } else {
                // Fallback to Service Account
                Log::info('Using Service Account for Google Drive');
                
                $serviceAccountPath = storage_path('app/credentials/google-service-account.json');
                
                if (!file_exists($serviceAccountPath)) {
                    throw new Exception("Service account file not found and no refresh token configured");
                }

                $this->client->setAuthConfig($serviceAccountPath);
                $this->client->addScope(Drive::DRIVE);
                $this->client->setApplicationName('VPS Live Server Control');
            }

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
            // Step 1: Get access token for the browser
            $accessToken = $this->client->getAccessToken();
            if (!$accessToken) {
                $this->client->fetchAccessTokenWithAssertion();
                $accessToken = $this->client->getAccessToken();
            }

            // Step 2: Create resumable upload session manually
            $metadata = [
                'name' => $fileName,
                'parents' => [$this->folderId]
            ];

            $postBody = json_encode($metadata);
            
            // Initialize resumable upload session
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken['access_token'],
                    'Content-Type: application/json; charset=UTF-8',
                    'X-Upload-Content-Type: ' . $mimeType,
                    'X-Upload-Content-Length: ' . $fileSize
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError = curl_error($ch);
            
            curl_close($ch);

            // Debug logging
            Log::info("Google Drive resumable upload response", [
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response_length' => strlen($response),
                'header_size' => $headerSize
            ]);

            if ($curlError) {
                throw new Exception("CURL error: $curlError");
            }

            if ($httpCode !== 200) {
                $headers = substr($response, 0, $headerSize);
                $body = substr($response, $headerSize);
                
                Log::error("Google Drive API error", [
                    'http_code' => $httpCode,
                    'headers' => $headers,
                    'body' => $body
                ]);
                
                throw new Exception("Failed to create resumable upload session: HTTP $httpCode - $body");
            }

            // Extract upload URL from Location header
            $headers = substr($response, 0, $headerSize);
            
            Log::info("Response headers:", ['headers' => $headers]);
            
            preg_match('/Location: ([^\r\n]+)/', $headers, $matches);
            
            if (!isset($matches[1])) {
                Log::error("No Location header found", ['full_headers' => $headers]);
                throw new Exception("No upload URL found in response headers");
            }

            $uploadUrl = trim($matches[1]);

            Log::info("Created resumable upload URL", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'upload_url' => substr($uploadUrl, 0, 50) . '...',
                'access_token_available' => !empty($accessToken['access_token'])
            ]);

            return [
                'success' => true,
                'upload_url' => $uploadUrl,
                'access_token' => $accessToken['access_token'], // Include access token for browser
                'chunk_size' => 8 * 1024 * 1024
            ];

        } catch (Exception $e) {
            Log::error('Failed to create resumable upload URL: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create resumable upload session for direct browser upload
     * Returns Google Drive upload URL that browser can upload to directly
     */
    public function createResumableUploadSession($fileName, $mimeType)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            // Create resumable upload session
            $metadata = [
                'name' => $fileName,
                'parents' => [$this->folderId]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => 0 // Browser will set this
            ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', $metadata);

            if ($response->successful()) {
                $uploadUrl = $response->header('Location');
                
                Log::info('Created Google Drive resumable upload session', [
                    'file_name' => $fileName,
                    'upload_url' => $uploadUrl
                ]);
                
                return $uploadUrl;
            } else {
                throw new \Exception('Failed to create upload session: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Failed to create resumable upload session: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get file metadata from Google Drive
     */
    public function getFileMetadata($fileId)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'fields' => 'id,name,size,mimeType,webViewLink,webContentLink,createdTime'
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to get file metadata: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Failed to get file metadata: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get access token for API calls
     */
    private function getAccessToken()
    {
        try {
            $accessToken = $this->client->getAccessToken();
            if (!$accessToken) {
                $this->client->fetchAccessTokenWithAssertion();
                $accessToken = $this->client->getAccessToken();
            }
            
            return $accessToken['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to get access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * STREAM UPLOAD - Tối ưu nhất!
     * Stream file trực tiếp từ input stream lên Google Drive
     * Không lưu file tạm, memory usage thấp
     */
    public function streamUploadFile($inputStream, $fileName, $mimeType, $fileSize)
    {
        try {
            Log::info("Starting stream upload to Google Drive", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType
            ]);

            // Step 1: Create resumable upload session
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new Exception('Could not get Google Drive API access token.');
            }

            $metadata = [
                'name' => $fileName,
                'parents' => [$this->folderId]
            ];

            // Initialize resumable upload session
            $initResponse = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'X-Upload-Content-Type' => $mimeType,
                    'X-Upload-Content-Length' => $fileSize
                ])
                ->timeout(30) // Add 30s timeout for session initialization
                ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', $metadata);

            if (!$initResponse->successful()) {
                throw new Exception('Failed to initialize resumable upload: ' . $initResponse->body());
            }

            $uploadUrl = $initResponse->header('Location');
            if (!$uploadUrl) {
                throw new Exception('No upload URL returned from Google Drive');
            }

            Log::info("Resumable upload session created", ['upload_url' => substr($uploadUrl, 0, 50) . '...']);

            // Step 2: Stream upload in chunks
            $chunkSize = 8 * 1024 * 1024; // 8MB chunks
            $uploaded = 0;

            while (!feof($inputStream) && $uploaded < $fileSize) {
                $remainingBytes = $fileSize - $uploaded;
                $currentChunkSize = min($chunkSize, $remainingBytes);
                
                // Read chunk from input stream
                $chunk = fread($inputStream, $currentChunkSize);
                if ($chunk === false || strlen($chunk) === 0) {
                    break;
                }

                $actualChunkSize = strlen($chunk);
                
                // Upload chunk
                $rangeEnd = $uploaded + $actualChunkSize - 1;
                $contentRange = "bytes {$uploaded}-{$rangeEnd}/{$fileSize}";

                Log::info("Uploading chunk", [
                    'content_range' => $contentRange,
                    'chunk_size' => $actualChunkSize
                ]);

                $chunkResponse = Http::withBody($chunk, $mimeType)
                    ->withHeaders([
                        'Content-Range' => $contentRange,
                    ])
                    ->timeout(120) // 120s timeout for chunk upload
                    ->put($uploadUrl);

                // Check response
                if ($chunkResponse->status() === 308) {
                    // Continue uploading - partial success
                    $uploaded += $actualChunkSize;
                    Log::info("Chunk uploaded successfully", ['uploaded' => $uploaded, 'total' => $fileSize]);
                } elseif ($chunkResponse->successful()) {
                    // Upload complete
                    $uploaded += $actualChunkSize;
                    $fileData = $chunkResponse->json();
                    
                    Log::info("Stream upload completed successfully", [
                        'file_id' => $fileData['id'],
                        'file_name' => $fileData['name'],
                        'total_uploaded' => $uploaded
                    ]);

                    return [
                        'success' => true,
                        'file_id' => $fileData['id'],
                        'file_name' => $fileData['name'],
                        'file_size' => $uploaded,
                        'web_view_link' => $fileData['webViewLink'] ?? null,
                        'download_link' => $fileData['webContentLink'] ?? null
                    ];
                } else {
                    throw new Exception('Chunk upload failed: ' . $chunkResponse->body());
                }
            }

            // If we reach here without completing, something went wrong
            throw new Exception('Upload completed but no final response received');

        } catch (Exception $e) {
            Log::error('Stream upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 