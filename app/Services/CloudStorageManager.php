<?php

namespace App\Services;

use App\Services\GoogleDriveService;
use App\Services\BunnyStorageService;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudStorageManager
{
    protected $provider;
    protected $service;

    public function __construct()
    {
        $this->provider = env('CLOUD_STORAGE_PROVIDER', 'bunny_cdn');
        $this->initializeService();
    }

    /**
     * Initialize the appropriate cloud storage service
     */
    private function initializeService()
    {
        try {
            switch ($this->provider) {
                case 'bunny_cdn':
                    $this->service = new BunnyStorageService();
                    break;
                case 'google_drive':
                    $this->service = new GoogleDriveService();
                    break;
                default:
                    $this->service = new BunnyStorageService(); // Default fallback
                    break;
            }

            Log::info("Cloud storage initialized", ['provider' => $this->provider]);

        } catch (Exception $e) {
            Log::error("Failed to initialize cloud storage", [
                'provider' => $this->provider,
                'error' => $e->getMessage()
            ]);

            // Fallback to alternative provider
            $this->fallbackToAlternative($e);
        }
    }

    /**
     * Fallback to alternative provider if primary fails
     */
    private function fallbackToAlternative(Exception $primaryError)
    {
        $alternativeProvider = $this->provider === 'google_drive' ? 'bunny_cdn' : 'google_drive';
        
        Log::warning("Attempting fallback to alternative provider", [
            'primary_provider' => $this->provider,
            'alternative_provider' => $alternativeProvider,
            'primary_error' => $primaryError->getMessage()
        ]);

        try {
            $this->provider = $alternativeProvider;
            
            switch ($alternativeProvider) {
                case 'bunny_cdn':
                    $this->service = new BunnyStorageService();
                    break;
                case 'google_drive':
                    $this->service = new GoogleDriveService();
                    break;
            }

            Log::info("Successfully fell back to alternative provider", [
                'provider' => $this->provider
            ]);

        } catch (Exception $e) {
            Log::error("Both cloud storage providers failed", [
                'primary_error' => $primaryError->getMessage(),
                'fallback_error' => $e->getMessage()
            ]);
            
            throw new Exception("All cloud storage providers failed. Primary: {$primaryError->getMessage()}, Fallback: {$e->getMessage()}");
        }
    }

    /**
     * Upload file using current provider
     */
    public function uploadFile($source, $fileName, $mimeType = null)
    {
        Log::info("Uploading file via cloud storage", [
            'provider' => $this->provider,
            'file_name' => $fileName
        ]);

        $result = $this->service->uploadFile($source, $fileName, $mimeType);
        
        // Add provider info to result
        if (isset($result['success']) && $result['success']) {
            $result['provider'] = $this->provider;
        }

        return $result;
    }

    /**
     * Stream upload file using current provider
     */
    public function streamUploadFile($inputStream, $fileName, $mimeType, $fileSize)
    {
        Log::info("Stream uploading file via cloud storage", [
            'provider' => $this->provider,
            'file_name' => $fileName,
            'file_size' => $fileSize
        ]);

        $result = $this->service->streamUploadFile($inputStream, $fileName, $mimeType, $fileSize);
        
        // Add provider info to result
        if (isset($result['success']) && $result['success']) {
            $result['provider'] = $this->provider;
        }

        return $result;
    }

    /**
     * Get direct download link using current provider
     */
    public function getDirectDownloadLink($fileId)
    {
        return $this->service->getDirectDownloadLink($fileId);
    }

    /**
     * Test connection using current provider
     */
    public function testConnection()
    {
        $result = $this->service->testConnection();
        
        if (isset($result['success'])) {
            $result['provider'] = $this->provider;
        }

        return $result;
    }

    /**
     * Get current provider name
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Switch to specific provider
     */
    public function switchProvider($provider)
    {
        if (!in_array($provider, ['google_drive', 'bunny_cdn'])) {
            throw new Exception("Unsupported provider: {$provider}");
        }

        $this->provider = $provider;
        $this->initializeService();
    }

    /**
     * Get provider status for both services
     */
    public function getProvidersStatus()
    {
        $status = [];

        // Google Drive removed - use Bunny CDN only
        $status['google_drive'] = [
            'success' => false,
            'error' => 'Google Drive support removed - use Bunny CDN'
        ];

        // Test Bunny.net
        try {
            $bunnyService = new BunnyStorageService();
            $status['bunny_cdn'] = $bunnyService->testConnection();
        } catch (Exception $e) {
            $status['bunny_cdn'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        $status['current_provider'] = $this->provider;
        return $status;
    }

    /**
     * Download file to VPS (for Bunny.net)
     */
    public function downloadToVps($remotePath, $localPath)
    {
        if ($this->provider === 'bunny_cdn' && method_exists($this->service, 'downloadToLocal')) {
            return $this->service->downloadToLocal($remotePath, $localPath);
        }

        return [
            'success' => false,
            'error' => 'Download to VPS not supported for current provider'
        ];
    }

    /**
     * Delete file from cloud storage
     */
    public function deleteFile($fileId)
    {
        if (method_exists($this->service, 'deleteFile')) {
            return $this->service->deleteFile($fileId);
        }

        return [
            'success' => false,
            'error' => 'Delete not supported for current provider'
        ];
    }
}
