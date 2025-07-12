<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\UserFile;
use App\Models\VpsServer;

class DirectStreamingService
{
    private const GOOGLE_DRIVE_API_URL = 'https://www.googleapis.com/drive/v3/files';
    private const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB chunks for HLS

    /**
     * Direct streaming is deprecated - use multistream instead
     */
    public function createDirectStreamPlaylist(UserFile $userFile, VpsServer $vpsServer): array
    {
        throw new \Exception('Direct streaming is deprecated. Use multistream system instead.');
    }

    /**
     * Lấy direct download URL từ Google Drive
     */
    private function getGoogleDriveDirectUrl(string $fileId): ?string
    {
        $cacheKey = "gdrive_direct_url_{$fileId}";
        
        // Cache URL trong 30 phút
        return Cache::remember($cacheKey, 1800, function () use ($fileId) {
            try {
                // Method 1: Thử Google Drive API
                $apiUrl = $this->tryGoogleDriveAPI($fileId);
                if ($apiUrl) return $apiUrl;

                // Method 2: Thử direct download URLs
                $directUrls = [
                    "https://drive.google.com/uc?export=download&id={$fileId}",
                    "https://docs.google.com/uc?export=download&id={$fileId}",
                    "https://drive.google.com/uc?id={$fileId}&export=download"
                ];

                foreach ($directUrls as $url) {
                    if ($this->validateDirectUrl($url)) {
                        return $url;
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error('Failed to get Google Drive direct URL', [
                    'file_id' => $fileId,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Google Drive API removed - use Bunny CDN instead
     */
    private function tryGoogleDriveAPI(string $fileId): ?string
    {
        return null;
    }

    /**
     * Validate direct URL
     */
    private function validateDirectUrl(string $url): bool
    {
        try {
            $response = Http::timeout(5)->head($url);
            return $response->successful() && $response->header('Content-Length') > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Tạo streaming endpoint trên VPS
     */
    private function createStreamingEndpoint(UserFile $userFile, VpsServer $vpsServer, string $directUrl): string
    {
        $sshService = app(SshService::class);
        $connection = $sshService->connect($vpsServer);

        if (!$connection) {
            throw new \Exception('Cannot connect to VPS server');
        }

        // Tạo streaming script trên VPS
        $streamingScript = $this->generateStreamingScript($userFile, $directUrl);
        $scriptPath = "/var/www/streaming/{$userFile->id}_stream.php";

        // Upload script lên VPS
        $this->uploadStreamingScript($sshService, $connection, $scriptPath, $streamingScript);

        // Return streaming endpoint URL
        return "https://{$vpsServer->domain}/streaming/{$userFile->id}_stream.php";
    }

    /**
     * Generate streaming script cho VPS
     */
    private function generateStreamingScript(UserFile $userFile, string $directUrl): string
    {
        return <<<PHP
<?php
// Direct streaming proxy script
header('Content-Type: application/vnd.apple.mpegurl');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

\$fileId = '{$userFile->id}';
\$directUrl = '{$directUrl}';
\$action = \$_GET['action'] ?? 'playlist';

switch (\$action) {
    case 'playlist':
        echo generateM3U8Playlist(\$fileId, \$directUrl);
        break;
    
    case 'segment':
        \$segmentIndex = (int)(\$_GET['segment'] ?? 0);
        streamSegment(\$directUrl, \$segmentIndex);
        break;
        
    default:
        http_response_code(404);
        echo 'Not found';
}

function generateM3U8Playlist(\$fileId, \$directUrl) {
    // Get file info để tính duration
    \$fileSize = getRemoteFileSize(\$directUrl);
    \$segmentDuration = 10; // 10 seconds per segment
    \$segmentSize = 2 * 1024 * 1024; // 2MB per segment
    \$totalSegments = ceil(\$fileSize / \$segmentSize);
    
    \$playlist = "#EXTM3U\n";
    \$playlist .= "#EXT-X-VERSION:3\n";
    \$playlist .= "#EXT-X-TARGETDURATION:\$segmentDuration\n";
    \$playlist .= "#EXT-X-MEDIA-SEQUENCE:0\n";
    
    for (\$i = 0; \$i < \$totalSegments; \$i++) {
        \$playlist .= "#EXTINF:\$segmentDuration.0,\n";
        \$playlist .= "?action=segment&segment=\$i\n";
    }
    
    \$playlist .= "#EXT-X-ENDLIST\n";
    return \$playlist;
}

function streamSegment(\$directUrl, \$segmentIndex) {
    \$segmentSize = 2 * 1024 * 1024; // 2MB
    \$startByte = \$segmentIndex * \$segmentSize;
    \$endByte = \$startByte + \$segmentSize - 1;
    
    // Stream range từ Google Drive
    \$context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Range: bytes=' . \$startByte . '-' . \$endByte,
                'User-Agent: Mozilla/5.0 (compatible; VPS-Streamer/1.0)'
            ]
        ]
    ]);
    
    header('Content-Type: video/mp2t'); // MPEG-TS for HLS
    header('Accept-Ranges: bytes');
    
    \$stream = fopen(\$directUrl, 'r', false, \$context);
    if (\$stream) {
        fpassthru(\$stream);
        fclose(\$stream);
    } else {
        http_response_code(500);
        echo 'Stream error';
    }
}

function getRemoteFileSize(\$url) {
    \$headers = get_headers(\$url, 1);
    return isset(\$headers['Content-Length']) ? (int)\$headers['Content-Length'] : 0;
}
?>
PHP;
    }

    /**
     * Upload streaming script lên VPS
     */
    private function uploadStreamingScript($sshService, $connection, string $scriptPath, string $scriptContent): void
    {
        // Tạo thư mục streaming
        $sshService->execute($connection, 'mkdir -p /var/www/streaming');
        
        // Upload script
        $tempFile = tempnam(sys_get_temp_dir(), 'streaming_script');
        file_put_contents($tempFile, $scriptContent);
        
        $uploadCommand = "scp '{$tempFile}' root@{$connection['host']}:'{$scriptPath}'";
        shell_exec($uploadCommand);
        
        // Set permissions
        $sshService->execute($connection, "chmod 644 '{$scriptPath}'");
        
        unlink($tempFile);
    }

    /**
     * Generate HLS playlist URL
     */
    private function generateHLSPlaylist(UserFile $userFile, VpsServer $vpsServer, string $streamingEndpoint): string
    {
        return $streamingEndpoint . '?action=playlist';
    }

    /**
     * Alternative: Sử dụng AWS CloudFront hoặc CDN
     */
    public function createCDNStreamingUrl(UserFile $userFile): string
    {
        $directUrl = $this->getGoogleDriveDirectUrl($userFile->google_drive_file_id);
        
        // Sử dụng CloudFront hoặc CDN khác để proxy
        $cdnUrl = config('services.cdn.base_url');
        $proxyUrl = $cdnUrl . '/proxy?url=' . urlencode($directUrl) . '&file_id=' . $userFile->id;
        
        return $proxyUrl;
    }

    /**
     * YouTube-DL style: Extract direct streaming URLs
     */
    public function extractStreamingUrls(string $googleDriveUrl): array
    {
        try {
            // Sử dụng yt-dlp hoặc youtube-dl để extract
            $command = "yt-dlp -g --no-warnings '{$googleDriveUrl}' 2>/dev/null";
            $output = shell_exec($command);
            
            if ($output) {
                $urls = array_filter(explode("\n", trim($output)));
                return [
                    'direct_urls' => $urls,
                    'method' => 'yt-dlp_extraction'
                ];
            }
        } catch (\Exception $e) {
            Log::warning('YT-DLP extraction failed', [
                'url' => $googleDriveUrl,
                'error' => $e->getMessage()
            ]);
        }
        
        return [];
    }

    /**
     * Tạo adaptive streaming với multiple qualities
     */
    public function createAdaptiveStream(UserFile $userFile, VpsServer $vpsServer): array
    {
        $directUrl = $this->getGoogleDriveDirectUrl($userFile->google_drive_file_id);
        
        // Tạo multiple quality streams
        $qualities = [
            '1080p' => ['bitrate' => '5000k', 'resolution' => '1920x1080'],
            '720p'  => ['bitrate' => '2500k', 'resolution' => '1280x720'],
            '480p'  => ['bitrate' => '1000k', 'resolution' => '854x480'],
            '360p'  => ['bitrate' => '500k',  'resolution' => '640x360']
        ];
        
        $masterPlaylist = "#EXTM3U\n#EXT-X-VERSION:6\n";
        
        foreach ($qualities as $quality => $config) {
            $variantUrl = $this->createQualityVariant($userFile, $vpsServer, $directUrl, $quality, $config);
            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH={$config['bitrate']},RESOLUTION={$config['resolution']}\n";
            $masterPlaylist .= "$variantUrl\n";
        }
        
        return [
            'master_playlist' => $masterPlaylist,
            'type' => 'adaptive_streaming'
        ];
    }

    /**
     * Tạo quality variant
     */
    private function createQualityVariant(UserFile $userFile, VpsServer $vpsServer, string $directUrl, string $quality, array $config): string
    {
        return "https://{$vpsServer->domain}/streaming/{$userFile->id}_stream.php?quality={$quality}";
    }

    /**
     * Performance monitoring
     */
    public function getStreamingStats($userFile): array
    {
        // Handle both UserFile model and stdClass/array for testing
        if (is_object($userFile) && isset($userFile->google_drive_file_id)) {
            $fileId = $userFile->google_drive_file_id;
            $cacheKey = "streaming_stats_{$fileId}";
        } elseif (is_object($userFile) && isset($userFile->id)) {
            $fileId = $userFile->google_drive_file_id ?? null;
            $cacheKey = "streaming_stats_{$userFile->id}";
        } else {
            throw new \InvalidArgumentException('Invalid userFile parameter');
        }
        
        if (!$fileId) {
            throw new \InvalidArgumentException('google_drive_file_id not found');
        }
        
        return Cache::remember($cacheKey, 300, function () use ($fileId) {
            $directUrl = $this->getGoogleDriveDirectUrl($fileId);
            
            if (!$directUrl) {
                return [
                    'error' => 'Cannot get direct URL',
                    'response_time_ms' => 0,
                    'url_accessible' => false,
                    'last_checked' => now()->toISOString()
                ];
            }
            
            // Test connection speed
            $startTime = microtime(true);
            $testResponse = Http::timeout(5)->head($directUrl);
            $responseTime = microtime(true) - $startTime;
            
            return [
                'response_time_ms' => round($responseTime * 1000, 2),
                'url_accessible' => $testResponse->successful(),
                'content_length' => $testResponse->header('Content-Length'),
                'direct_url' => $directUrl,
                'last_checked' => now()->toISOString()
            ];
        });
    }
} 