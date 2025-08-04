<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BunnyStreamService
{
    private $apiKey;
    private $apiUrl;
    private $videoLibraryId;
    private $cdnHostname;

    public function __construct()
    {
        $this->apiKey = config('bunnycdn.stream_api_key');
        $this->apiUrl = config('bunnycdn.stream_api_url', 'https://video.bunnycdn.com');
        $this->videoLibraryId = config('bunnycdn.video_library_id');
        $this->cdnHostname = config('bunnycdn.stream_cdn_hostname');
    }

    /**
     * Upload video to BunnyCDN Stream Library
     */
    public function uploadVideo($filePath, $title, $userId = null)
    {
        try {
            Log::info("Starting BunnyCDN Stream upload", [
                'file_path' => $filePath,
                'title' => $title,
                'user_id' => $userId
            ]);

            // Step 1: Create video object
            $videoData = $this->createVideo($title);
            if (!$videoData['success']) {
                throw new Exception("Failed to create video: " . $videoData['error']);
            }

            $videoId = $videoData['video_id'];
            Log::info("Video object created", ['video_id' => $videoId]);

            // Step 2: Upload video file
            $uploadResult = $this->uploadVideoFile($videoId, $filePath);
            if (!$uploadResult['success']) {
                throw new Exception("Failed to upload video file: " . $uploadResult['error']);
            }

            Log::info("Video uploaded successfully", ['video_id' => $videoId]);

            return [
                'success' => true,
                'video_id' => $videoId,
                'video_data' => $videoData['data'],
                'hls_url' => $this->getHlsUrl($videoId),
                'mp4_url' => $this->getMp4Url($videoId),
                'thumbnail_url' => $this->getThumbnailUrl($videoId)
            ];

        } catch (Exception $e) {
            Log::error("BunnyCDN Stream upload failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create video object in Stream Library
     */
    public function createVideo($title)
    {
        try {
            $url = "{$this->apiUrl}/library/{$this->videoLibraryId}/videos";
            \Log::info('Creating video in BunnyCDN Stream', [
                'title' => $title,
                'url' => $url,
                'library_id' => $this->videoLibraryId,
                'has_api_key' => !empty($this->apiKey)
            ]);

            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($url, [
                'title' => $title
            ]);

            \Log::info('BunnyCDN Stream API response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                \Log::info('Video created successfully', [
                    'video_id' => $data['guid'] ?? 'no_guid',
                    'data' => $data
                ]);
                return [
                    'success' => true,
                    'video_id' => $data['guid'],
                    'data' => $data
                ];
            } else {
                $error = "HTTP {$response->status()}: " . $response->body();
                \Log::error('Failed to create video', ['error' => $error]);
                return [
                    'success' => false,
                    'error' => $error
                ];
            }

        } catch (Exception $e) {
            \Log::error('Exception creating video', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload video file to existing video object
     */
    public function uploadVideoFile($videoId, $filePath)
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                throw new Exception("Cannot read file content");
            }

            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/octet-stream'
            ])->withBody($fileContent)
              ->put("{$this->apiUrl}/library/{$this->videoLibraryId}/videos/{$videoId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get video information
     */
    public function getVideo($videoId)
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey
            ])->get("{$this->apiUrl}/library/{$this->videoLibraryId}/videos/{$videoId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete video from Stream Library
     */
    public function deleteVideo($videoId)
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey
            ])->delete("{$this->apiUrl}/library/{$this->videoLibraryId}/videos/{$videoId}");

            if ($response->successful()) {
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get HLS playlist URL for streaming
     */
    public function getHlsUrl($videoId)
    {
        return "https://{$this->cdnHostname}/{$videoId}/playlist.m3u8";
    }

    /**
     * Get MP4 direct URL
     */
    public function getMp4Url($videoId)
    {
        return "https://{$this->cdnHostname}/{$videoId}/play_1080p.mp4";
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl($videoId)
    {
        return "https://{$this->cdnHostname}/{$videoId}/thumbnail.jpg";
    }

    /**
     * Get video status (processing, finished, error)
     */
    public function getVideoStatus($videoId)
    {
        $result = $this->getVideo($videoId);
        if ($result['success']) {
            return [
                'success' => true,
                'status' => $result['data']['status'] ?? 'unknown',
                'encoding_progress' => $result['data']['encodeProgress'] ?? 0
            ];
        }
        return $result;
    }

    /**
     * Wait for video processing to complete
     */
    public function waitForProcessing($videoId, $maxWaitSeconds = 300)
    {
        $startTime = time();
        
        while ((time() - $startTime) < $maxWaitSeconds) {
            $status = $this->getVideoStatus($videoId);
            
            if (!$status['success']) {
                return $status;
            }

            $videoStatus = $status['status'];
            
            if ($videoStatus === 'finished') {
                return [
                    'success' => true,
                    'status' => 'finished',
                    'message' => 'Video processing completed'
                ];
            } elseif ($videoStatus === 'error') {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error' => 'Video processing failed'
                ];
            }

            // Still processing, wait and check again
            sleep(5);
        }

        return [
            'success' => false,
            'status' => 'timeout',
            'error' => 'Video processing timeout'
        ];
    }

    /**
     * Get video library statistics
     */
    public function getLibraryStats()
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey
            ])->get("{$this->apiUrl}/library/{$this->videoLibraryId}/statistics");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if Stream Library is configured
     */
    public function isConfigured()
    {
        return !empty($this->apiKey) && 
               !empty($this->videoLibraryId) && 
               !empty($this->cdnHostname);
    }

    /**
     * Test Stream Library connection
     */
    public function testConnection()
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'BunnyCDN Stream Library not configured'
                ];
            }

            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey
            ])->get("{$this->apiUrl}/library/{$this->videoLibraryId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'BunnyCDN Stream Library connection successful',
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
