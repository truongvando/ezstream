<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YoutubeApiService
{
    private string $apiKey;
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';
    private int $maxResults = 50; // YouTube API limit per request
    private $rotationService;

    public function __construct()
    {
        $this->rotationService = new \App\Services\ApiKeyRotationService();
        $this->apiKey = $this->rotationService->getCurrentYouTubeApiKey();

        if (!$this->apiKey) {
            throw new \Exception('YouTube API key not configured');
        }
    }

    /**
     * Get channel information by channel ID
     */
    public function getChannelInfo(string $channelId): ?array
    {
        try {
            Log::info('Getting channel info from YouTube API', ['channel_id' => $channelId]);

            $response = $this->makeApiRequest("{$this->baseUrl}/channels", [
                'key' => $this->apiKey,
                'id' => $channelId,
                'part' => 'snippet,statistics',
                'maxResults' => 1
            ]);

            if (!$response) {
                return null;
            }

            $data = $response;

            if (empty($data['items'])) {
                Log::warning('Channel not found', ['channel_id' => $channelId]);
                return null;
            }

            $channel = $data['items'][0];

            $result = [
                'channel_id' => $channel['id'],
                'channel_name' => $channel['snippet']['title'],
                'description' => $channel['snippet']['description'] ?? '',
                'thumbnail_url' => $channel['snippet']['thumbnails']['high']['url'] ?? null,
                'country' => $channel['snippet']['country'] ?? null,
                'channel_created_at' => $channel['snippet']['publishedAt'],
                'channel_url' => "https://www.youtube.com/channel/{$channel['id']}",
                'channel_handle' => $channel['snippet']['customUrl'] ?? null,
                'subscriber_count' => (int) ($channel['statistics']['subscriberCount'] ?? 0),
                'video_count' => (int) ($channel['statistics']['videoCount'] ?? 0),
                'view_count' => (int) ($channel['statistics']['viewCount'] ?? 0),
            ];

            Log::info('Successfully retrieved channel info', [
                'channel_id' => $channelId,
                'channel_name' => $result['channel_name'],
                'subscriber_count' => $result['subscriber_count']
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error getting channel info', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get channel information by username or handle
     */
    public function getChannelByUsername(string $username): ?array
    {
        try {
            // Remove @ if present
            $username = ltrim($username, '@');
            
            $response = Http::get("{$this->baseUrl}/channels", [
                'key' => $this->apiKey,
                'forUsername' => $username,
                'part' => 'snippet,statistics,brandingSettings',
                'maxResults' => 1
            ]);

            if (!$response->successful()) {
                Log::error('YouTube API error getting channel by username', [
                    'username' => $username,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            
            if (empty($data['items'])) {
                Log::warning('Channel not found by username', ['username' => $username]);
                return null;
            }

            $channel = $data['items'][0];
            
            return [
                'channel_id' => $channel['id'],
                'channel_name' => $channel['snippet']['title'],
                'description' => $channel['snippet']['description'] ?? '',
                'thumbnail_url' => $channel['snippet']['thumbnails']['high']['url'] ?? null,
                'country' => $channel['snippet']['country'] ?? null,
                'channel_created_at' => $channel['snippet']['publishedAt'],
                'channel_url' => "https://www.youtube.com/channel/{$channel['id']}",
                'channel_handle' => $channel['snippet']['customUrl'] ?? null,
                'subscriber_count' => (int) ($channel['statistics']['subscriberCount'] ?? 0),
                'video_count' => (int) ($channel['statistics']['videoCount'] ?? 0),
                'view_count' => (int) ($channel['statistics']['viewCount'] ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('Error getting channel by username', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get multiple channels info in batch (up to 50 per request)
     */
    public function getBatchChannelsInfo(array $channelIds): array
    {
        if (empty($channelIds)) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($channelIds, $this->maxResults);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::get("{$this->baseUrl}/channels", [
                    'key' => $this->apiKey,
                    'id' => implode(',', $chunk),
                    'part' => 'snippet,statistics,brandingSettings',
                    'maxResults' => $this->maxResults
                ]);

                if (!$response->successful()) {
                    Log::error('YouTube API error getting batch channels', [
                        'channel_ids' => $chunk,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    continue;
                }

                $data = $response->json();
                
                foreach ($data['items'] ?? [] as $channel) {
                    $results[$channel['id']] = [
                        'channel_id' => $channel['id'],
                        'channel_name' => $channel['snippet']['title'],
                        'description' => $channel['snippet']['description'] ?? '',
                        'thumbnail_url' => $channel['snippet']['thumbnails']['high']['url'] ?? null,
                        'country' => $channel['snippet']['country'] ?? null,
                        'channel_created_at' => $channel['snippet']['publishedAt'],
                        'channel_url' => "https://www.youtube.com/channel/{$channel['id']}",
                        'channel_handle' => $channel['snippet']['customUrl'] ?? null,
                        'subscriber_count' => (int) ($channel['statistics']['subscriberCount'] ?? 0),
                        'video_count' => (int) ($channel['statistics']['videoCount'] ?? 0),
                        'view_count' => (int) ($channel['statistics']['viewCount'] ?? 0),
                    ];
                }

                // Rate limiting - YouTube allows 100 requests per 100 seconds
                sleep(1);
                
            } catch (\Exception $e) {
                Log::error('Error getting batch channels info', [
                    'channel_ids' => $chunk,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Extract channel ID from various YouTube URL formats
     * Based on working JavaScript implementation
     */
    public function extractChannelId(string $input): ?string
    {
        // Remove whitespace
        $input = trim($input);

        Log::info('Extracting channel ID from input', ['input' => $input]);

        // Pattern 1: Extract UC... from YouTube URLs (like the working JS code)
        $urlPattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:channel\/|user\/|c\/|@)|youtu\.be\/)(UC[a-zA-Z0-9_-]{22})/';
        if (preg_match($urlPattern, $input, $matches)) {
            Log::info('Found channel ID in URL', ['channel_id' => $matches[1]]);
            return $matches[1];
        }

        // Pattern 1.5: Extract from video URL and get channel from video
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]+)/', $input, $matches)) {
            $videoId = $matches[1];
            Log::info('Extracting channel from video URL', ['video_id' => $videoId]);
            return $this->getChannelIdFromVideo($videoId);
        }

        // Pattern 2: Direct Channel ID (UC...)
        if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $input)) {
            Log::info('Found direct channel ID', ['channel_id' => $input]);
            return $input;
        }

        // Pattern 3: Try to resolve @username or username via API
        $username = $input;

        // Remove @ if present
        if (strpos($username, '@') === 0) {
            $username = substr($username, 1);
        }

        // Extract username from URL patterns (improved regex)
        if (preg_match('/youtube\.com\/@([a-zA-Z0-9_.-]+)/', $input, $matches)) {
            $username = $matches[1];
            Log::info('Extracted handle from URL', ['handle' => $username]);
            return $this->resolveHandleToChannelId($username);
        } elseif (preg_match('/youtube\.com\/(?:c|user)\/([a-zA-Z0-9_-]+)/', $input, $matches)) {
            $username = $matches[1];
            Log::info('Extracted username from URL', ['username' => $username]);
        }

        if ($username && $username !== $input) {
            Log::info('Attempting to resolve username', ['username' => $username]);
            return $this->resolveUsernameToChannelId($username);
        }

        Log::warning('No valid pattern found for input', ['input' => $input]);
        return null;
    }

    /**
     * Get channel ID from video ID
     */
    private function getChannelIdFromVideo(string $videoId): ?string
    {
        try {
            Log::info('Getting channel ID from video', ['video_id' => $videoId]);

            $response = $this->makeApiRequest("{$this->baseUrl}/videos", [
                'key' => $this->apiKey,
                'id' => $videoId,
                'part' => 'snippet',
                'maxResults' => 1
            ]);

            if (!$response || empty($response['items'])) {
                Log::warning('Video not found', ['video_id' => $videoId]);
                return null;
            }

            $channelId = $response['items'][0]['snippet']['channelId'] ?? null;

            if ($channelId) {
                Log::info('Successfully extracted channel ID from video', [
                    'video_id' => $videoId,
                    'channel_id' => $channelId
                ]);
            }

            return $channelId;

        } catch (\Exception $e) {
            Log::error('Error getting channel ID from video', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Resolve username to channel ID
     */
    private function resolveUsernameToChannelId(string $username): ?string
    {
        Log::info('Resolving username to channel ID', ['username' => $username]);

        $channelInfo = $this->getChannelByUsername($username);

        if ($channelInfo && isset($channelInfo['channel_id'])) {
            Log::info('Successfully resolved username', [
                'username' => $username,
                'channel_id' => $channelInfo['channel_id'],
                'channel_name' => $channelInfo['channel_name']
            ]);
            return $channelInfo['channel_id'];
        }

        Log::warning('Failed to resolve username', ['username' => $username]);
        return null;
    }

    /**
     * Resolve handle (@username) to channel ID using search
     */
    private function resolveHandleToChannelId(string $handle): ?string
    {
        try {
            Log::info('Resolving handle to channel ID', ['handle' => $handle]);

            // Try search API to find channel by handle
            $response = $this->makeApiRequest("{$this->baseUrl}/search", [
                'key' => $this->apiKey,
                'q' => "@{$handle}",
                'type' => 'channel',
                'part' => 'snippet',
                'maxResults' => 5
            ]);

            if (!$response || empty($response['items'])) {
                Log::warning('No channels found for handle', ['handle' => $handle]);
                return null;
            }

            // Look for exact match in custom URL or title
            foreach ($response['items'] as $item) {
                $channelId = $item['id']['channelId'] ?? null;
                $customUrl = $item['snippet']['customUrl'] ?? '';

                // Check if custom URL matches handle
                if (strtolower($customUrl) === strtolower("@{$handle}") ||
                    strtolower($customUrl) === strtolower($handle)) {
                    Log::info('Found channel by custom URL match', [
                        'handle' => $handle,
                        'channel_id' => $channelId,
                        'custom_url' => $customUrl
                    ]);
                    return $channelId;
                }
            }

            // If no exact match, return first result
            $firstChannelId = $response['items'][0]['id']['channelId'] ?? null;
            if ($firstChannelId) {
                Log::info('Using first search result for handle', [
                    'handle' => $handle,
                    'channel_id' => $firstChannelId
                ]);
            }

            return $firstChannelId;

        } catch (\Exception $e) {
            Log::error('Error resolving handle to channel ID', [
                'handle' => $handle,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get videos from a channel
     */
    public function getChannelVideos(string $channelId, int $maxResults = 50, string $pageToken = ''): array
    {
        try {
            Log::info('Getting channel videos', ['channel_id' => $channelId, 'max_results' => $maxResults]);

            $params = [
                'key' => $this->apiKey,
                'channelId' => $channelId,
                'part' => 'snippet',
                'order' => 'date',
                'type' => 'video',
                'maxResults' => min($maxResults, 50)
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::timeout(30)->get("{$this->baseUrl}/search", $params);

            Log::info('YouTube search API response', [
                'status' => $response->status(),
                'channel_id' => $channelId
            ]);

            if (!$response->successful()) {
                Log::error('YouTube API error getting channel videos', [
                    'channel_id' => $channelId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'videos' => [],
                    'nextPageToken' => null,
                    'totalResults' => 0
                ];
            }

            $data = $response->json();
            $videos = [];

            foreach ($data['items'] ?? [] as $item) {
                if (isset($item['id']['videoId'])) {
                    $videos[] = [
                        'video_id' => $item['id']['videoId'],
                        'title' => $item['snippet']['title'],
                        'description' => $item['snippet']['description'] ?? '',
                        'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'] ??
                                         ($item['snippet']['thumbnails']['medium']['url'] ??
                                         ($item['snippet']['thumbnails']['default']['url'] ?? null)),
                        'published_at' => $item['snippet']['publishedAt'],
                    ];
                }
            }

            Log::info('Found videos for channel', [
                'channel_id' => $channelId,
                'video_count' => count($videos)
            ]);

            return [
                'videos' => $videos,
                'nextPageToken' => $data['nextPageToken'] ?? null,
                'totalResults' => $data['pageInfo']['totalResults'] ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('Error getting channel videos', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'videos' => [],
                'nextPageToken' => null,
                'totalResults' => 0
            ];
        }
    }

    /**
     * Get video statistics in batch
     */
    public function getBatchVideoStats(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($videoIds, $this->maxResults);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::get("{$this->baseUrl}/videos", [
                    'key' => $this->apiKey,
                    'id' => implode(',', $chunk),
                    'part' => 'statistics,contentDetails,status',
                    'maxResults' => $this->maxResults
                ]);

                if (!$response->successful()) {
                    Log::error('YouTube API error getting batch video stats', [
                        'video_ids' => $chunk,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    continue;
                }

                $data = $response->json();

                foreach ($data['items'] ?? [] as $video) {
                    $results[$video['id']] = [
                        'video_id' => $video['id'],
                        'view_count' => (int) ($video['statistics']['viewCount'] ?? 0),
                        'like_count' => (int) ($video['statistics']['likeCount'] ?? 0),
                        'comment_count' => (int) ($video['statistics']['commentCount'] ?? 0),
                        'duration_seconds' => $this->parseDuration($video['contentDetails']['duration'] ?? ''),
                        'status' => $this->getVideoStatus($video['status'] ?? []),
                    ];
                }

                // Rate limiting
                sleep(1);

            } catch (\Exception $e) {
                Log::error('Error getting batch video stats', [
                    'video_ids' => $chunk,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Parse ISO 8601 duration to seconds
     */
    private function parseDuration(string $duration): int
    {
        if (empty($duration)) {
            return 0;
        }

        // Parse ISO 8601 duration (PT4M13S = 4 minutes 13 seconds)
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Determine video status
     */
    private function getVideoStatus(array $status): string
    {
        $privacyStatus = $status['privacyStatus'] ?? 'public';

        switch ($privacyStatus) {
            case 'public':
                return 'live';
            case 'private':
                return 'private';
            case 'unlisted':
                return 'unlisted';
            default:
                return 'dead';
        }
    }

    /**
     * Check API quota usage (cached for 1 hour)
     */
    public function checkQuotaUsage(): array
    {
        $cacheKey = 'youtube_api_quota_' . date('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () {
            // This is a simple estimation - YouTube doesn't provide real quota usage
            // Each request costs different quota units
            return [
                'estimated_usage' => 0,
                'daily_limit' => 10000, // Default quota
                'reset_time' => now()->addDay()->startOfDay(),
            ];
        });
    }

    /**
     * Get comprehensive video details for AI analysis
     */
    public function getVideoDetailsForAI(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        try {
            Log::info('Getting comprehensive video details for AI', ['video_count' => count($videoIds)]);

            $chunks = array_chunk($videoIds, 50); // API limit
            $allVideos = [];

            foreach ($chunks as $chunk) {
                $response = Http::timeout(30)->get("{$this->baseUrl}/videos", [
                    'key' => $this->apiKey,
                    'id' => implode(',', $chunk),
                    'part' => 'snippet,statistics,contentDetails,status'
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    foreach ($data['items'] ?? [] as $item) {
                        // Parse duration from ISO 8601 format (PT4M13S -> 4:13)
                        $duration = $this->parseIsoDuration($item['contentDetails']['duration'] ?? '');

                        // Extract tags
                        $tags = $item['snippet']['tags'] ?? [];

                        // Get publish time details
                        $publishedAt = new \DateTime($item['snippet']['publishedAt']);
                        $publishHour = $publishedAt->format('H');
                        $publishDay = $publishedAt->format('N'); // 1=Monday, 7=Sunday
                        $publishDayName = $publishedAt->format('l'); // Monday, Tuesday, etc.

                        $allVideos[] = [
                            'video_id' => $item['id'],
                            'title' => $item['snippet']['title'],
                            'description' => $item['snippet']['description'] ?? '',
                            'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'] ??
                                             ($item['snippet']['thumbnails']['medium']['url'] ??
                                             ($item['snippet']['thumbnails']['default']['url'] ?? null)),
                            'published_at' => $item['snippet']['publishedAt'],
                            'publish_hour' => (int) $publishHour,
                            'publish_day' => (int) $publishDay,
                            'publish_day_name' => $publishDayName,
                            'duration' => $duration,
                            'duration_seconds' => $this->isoDurationToSeconds($item['contentDetails']['duration'] ?? ''),
                            'view_count' => (int) ($item['statistics']['viewCount'] ?? 0),
                            'like_count' => (int) ($item['statistics']['likeCount'] ?? 0),
                            'comment_count' => (int) ($item['statistics']['commentCount'] ?? 0),
                            'tags' => $tags,
                            'tag_count' => count($tags),
                            'category_id' => $item['snippet']['categoryId'] ?? null,
                            'default_language' => $item['snippet']['defaultLanguage'] ?? null,
                            'privacy_status' => $item['status']['privacyStatus'] ?? 'public',
                            'upload_status' => $item['status']['uploadStatus'] ?? 'processed',
                        ];
                    }
                } else {
                    Log::error('Failed to get video details for AI', [
                        'chunk_size' => count($chunk),
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

                // Rate limiting
                sleep(1);
            }

            Log::info('Retrieved comprehensive video details', ['video_count' => count($allVideos)]);
            return $allVideos;

        } catch (\Exception $e) {
            Log::error('Error getting video details for AI', [
                'video_ids' => $videoIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Parse ISO 8601 duration to readable format
     */
    private function parseIsoDuration(string $duration): string
    {
        if (empty($duration)) return '0:00';

        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }

    /**
     * Convert ISO 8601 duration to total seconds
     */
    private function isoDurationToSeconds(string $duration): int
    {
        if (empty($duration)) return 0;

        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Make API request with auto-rotation on quota exceeded
     */
    private function makeApiRequest(string $url, array $params, int $retryCount = 0): ?array
    {
        try {
            $response = Http::timeout(30)->get($url, $params);

            Log::info('YouTube API response', [
                'status' => $response->status(),
                'url' => $url
            ]);

            if (!$response->successful()) {
                $responseData = $response->json();

                // Check if quota exceeded and try to rotate API key
                if ($response->status() === 403 &&
                    isset($responseData['error']['message']) &&
                    strpos($responseData['error']['message'], 'quota') !== false &&
                    $retryCount < 2) {

                    Log::warning('YouTube API quota exceeded, attempting key rotation', [
                        'current_key_preview' => substr($this->apiKey, 0, 10) . '...',
                        'retry_count' => $retryCount
                    ]);

                    $rotationResult = $this->rotationService->rotateToNextKey();

                    if ($rotationResult['success']) {
                        // Update current API key and retry
                        $this->apiKey = $this->rotationService->getCurrentYouTubeApiKey();
                        $params['key'] = $this->apiKey;

                        Log::info('API key rotated successfully, retrying request', [
                            'new_key_preview' => substr($this->apiKey, 0, 10) . '...'
                        ]);

                        return $this->makeApiRequest($url, $params, $retryCount + 1);
                    }
                }

                Log::error('YouTube API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'retry_count' => $retryCount
                ]);

                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('YouTube API request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ]);

            return null;
        }
    }
}
