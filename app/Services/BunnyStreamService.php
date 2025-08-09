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
            $url = "{$this->apiUrl}/library/{$this->videoLibraryId}/videos/{$videoId}";

            // Log API call details
            Log::info("🌐 [BunnyAPI] Making API call", [
                'url' => $url,
                'video_id' => $videoId,
                'library_id' => $this->videoLibraryId,
                'api_key_prefix' => substr($this->apiKey, 0, 8) . '...',
                'method' => 'GET'
            ]);

            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Accept' => 'application/json'
            ])->get($url);

            // Log response details
            Log::info("📥 [BunnyAPI] Response received", [
                'video_id' => $videoId,
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'response_size' => strlen($response->body()),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Log parsed data
                Log::info("📊 [BunnyAPI] Parsed response data", [
                    'video_id' => $videoId,
                    'status' => $data['status'] ?? 'N/A',
                    'encode_progress' => $data['encodeProgress'] ?? 'N/A',
                    'title' => $data['title'] ?? 'N/A',
                    'length' => $data['length'] ?? 'N/A',
                    'date_uploaded' => $data['dateUploaded'] ?? 'N/A',
                    'all_fields' => array_keys($data)
                ]);

                return [
                    'success' => true,
                    'data' => $data
                ];
            } else {
                $errorBody = $response->body();
                Log::error("❌ [BunnyAPI] API error response", [
                    'video_id' => $videoId,
                    'status_code' => $response->status(),
                    'error_body' => $errorBody
                ]);

                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $errorBody
                ];
            }

        } catch (Exception $e) {
            Log::error("💥 [BunnyAPI] Exception during API call", [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete video from Stream Library with retry mechanism
     */
    public function deleteVideo($videoId, int $maxRetries = 3)
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info("🗑️ [BunnyStream] Attempting to delete video {$videoId} (attempt {$attempt}/{$maxRetries})");

                $response = Http::timeout(30)
                    ->withHeaders([
                        'AccessKey' => $this->apiKey
                    ])
                    ->delete("{$this->apiUrl}/library/{$this->videoLibraryId}/videos/{$videoId}");

                if ($response->successful()) {
                    Log::info("✅ [BunnyStream] Successfully deleted video {$videoId}");
                    return [
                        'success' => true,
                        'attempt' => $attempt
                    ];
                }

                // Handle specific HTTP status codes
                $statusCode = $response->status();
                $responseBody = $response->body();

                if ($statusCode === 404) {
                    // Video already deleted or doesn't exist
                    Log::info("ℹ️ [BunnyStream] Video {$videoId} not found (already deleted)");
                    return [
                        'success' => true,
                        'message' => 'Video already deleted or not found',
                        'attempt' => $attempt
                    ];
                }

                if ($statusCode === 429) {
                    // Rate limited - wait longer before retry
                    $waitTime = min(60, $attempt * 10); // Max 60 seconds
                    Log::warning("⏳ [BunnyStream] Rate limited, waiting {$waitTime}s before retry");
                    sleep($waitTime);
                    continue;
                }

                if ($statusCode >= 500) {
                    // Server error - retry
                    $waitTime = $attempt * 2; // Exponential backoff
                    Log::warning("⚠️ [BunnyStream] Server error {$statusCode}, retrying in {$waitTime}s");
                    sleep($waitTime);
                    continue;
                }

                // Client error (4xx) - don't retry
                Log::error("❌ [BunnyStream] Client error deleting video {$videoId}: HTTP {$statusCode}");
                return [
                    'success' => false,
                    'error' => "HTTP {$statusCode}: {$responseBody}",
                    'retry_attempted' => $attempt > 1
                ];

            } catch (Exception $e) {
                Log::error("❌ [BunnyStream] Exception deleting video {$videoId} (attempt {$attempt}): {$e->getMessage()}");

                if ($attempt >= $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'attempts' => $attempt
                    ];
                }

                // Wait before retry
                $waitTime = $attempt * 2;
                sleep($waitTime);
            }
        }

        return [
            'success' => false,
            'error' => "Failed after {$maxRetries} attempts",
            'attempts' => $maxRetries
        ];
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
        Log::info("🔍 [BunnyAPI] Getting video status", ['video_id' => $videoId]);

        $result = $this->getVideo($videoId);
        if ($result['success']) {
            $numericStatus = $result['data']['status'] ?? 0;
            $statusString = $this->mapBunnyStatus($numericStatus);
            $encodingProgress = $result['data']['encodeProgress'] ?? 0;

            Log::info("🎯 [BunnyAPI] Status mapping result", [
                'video_id' => $videoId,
                'numeric_status' => $numericStatus,
                'string_status' => $statusString,
                'encoding_progress' => $encodingProgress,
                'mapping' => [
                    '0' => 'created',
                    '1' => 'processing',
                    '2' => 'error',
                    '3' => 'finished',
                    '4' => 'finished'
                ]
            ]);

            return [
                'success' => true,
                'status' => $statusString,
                'numeric_status' => $numericStatus,
                'encoding_progress' => $encodingProgress
            ];
        }

        Log::error("❌ [BunnyAPI] Failed to get video status", [
            'video_id' => $videoId,
            'error' => $result['error'] ?? 'Unknown error'
        ]);

        return $result;
    }

    /**
     * Map Bunny numeric status to string
     */
    private function mapBunnyStatus($numericStatus): string
    {
        return match($numericStatus) {
            0 => 'created',      // Video created, no file uploaded yet
            1 => 'processing',   // Video is being processed/encoded
            2 => 'error',        // Processing failed
            3 => 'finished',     // Processing completed successfully
            4 => 'finished',     // Processing completed successfully (alternative)
            default => 'unknown'
        };
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
