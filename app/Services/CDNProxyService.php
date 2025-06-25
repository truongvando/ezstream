<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CDNProxyService
{
    private const CLOUDFLARE_WORKERS_URL = 'https://your-worker.your-subdomain.workers.dev';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Tạo CloudFlare Worker để proxy Google Drive
     */
    public function createCloudFlareWorker(): string
    {
        return <<<JAVASCRIPT
// CloudFlare Worker Script
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const url = new URL(request.url)
  const googleDriveUrl = url.searchParams.get('url')
  const fileId = url.searchParams.get('file_id')
  
  if (!googleDriveUrl) {
    return new Response('Missing URL parameter', { status: 400 })
  }
  
  // Cache key
  const cacheKey = `gdrive_proxy_\${fileId}`
  const cache = caches.default
  
  // Check cache first
  let response = await cache.match(request)
  if (response) {
    return response
  }
  
  try {
    // Fetch từ Google Drive với headers optimized
    const driveResponse = await fetch(googleDriveUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (compatible; CloudFlare-Worker/1.0)',
        'Referer': 'https://drive.google.com/',
        'Range': request.headers.get('Range') || ''
      }
    })
    
    if (!driveResponse.ok) {
      return new Response('Failed to fetch from Google Drive', { status: 502 })
    }
    
    // Create response với CORS headers
    response = new Response(driveResponse.body, {
      status: driveResponse.status,
      statusText: driveResponse.statusText,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers': 'Range, Content-Type',
        'Content-Type': driveResponse.headers.get('Content-Type') || 'video/mp4',
        'Content-Length': driveResponse.headers.get('Content-Length') || '',
        'Accept-Ranges': 'bytes',
        'Cache-Control': 'public, max-age=3600',
        'CDN-Cache-Control': 'public, max-age=86400'
      }
    })
    
    // Cache response nếu không phải range request
    if (!request.headers.get('Range')) {
      event.waitUntil(cache.put(request, response.clone()))
    }
    
    return response
    
  } catch (error) {
    return new Response('Proxy error: ' + error.message, { status: 500 })
  }
}
JAVASCRIPT;
    }

    /**
     * Tạo proxy URL qua CloudFlare
     */
    public function createProxyUrl(string $googleDriveUrl, string $fileId): string
    {
        $baseUrl = config('services.cloudflare.worker_url', self::CLOUDFLARE_WORKERS_URL);
        
        return $baseUrl . '?' . http_build_query([
            'url' => $googleDriveUrl,
            'file_id' => $fileId,
            'cache' => time() // Cache busting
        ]);
    }

    /**
     * Sử dụng AWS CloudFront
     */
    public function createCloudFrontDistribution(string $googleDriveUrl): array
    {
        // AWS SDK để tạo CloudFront distribution
        $distributionConfig = [
            'CallerReference' => 'gdrive-proxy-' . uniqid(),
            'Comment' => 'Google Drive Proxy Distribution',
            'DefaultCacheBehavior' => [
                'TargetOriginId' => 'google-drive-origin',
                'ViewerProtocolPolicy' => 'redirect-to-https',
                'MinTTL' => 0,
                'DefaultTTL' => 3600,
                'MaxTTL' => 86400,
                'ForwardedValues' => [
                    'QueryString' => true,
                    'Headers' => [
                        'Range',
                        'If-Range',
                        'If-Modified-Since'
                    ]
                ]
            ],
            'Origins' => [
                'Quantity' => 1,
                'Items' => [
                    [
                        'Id' => 'google-drive-origin',
                        'DomainName' => 'drive.google.com',
                        'CustomOriginConfig' => [
                            'HTTPPort' => 443,
                            'HTTPSPort' => 443,
                            'OriginProtocolPolicy' => 'https-only',
                            'OriginSslProtocols' => [
                                'Quantity' => 1,
                                'Items' => ['TLSv1.2']
                            ]
                        ]
                    ]
                ]
            ],
            'Enabled' => true,
            'PriceClass' => 'PriceClass_100' // US, Europe only
        ];

        return $distributionConfig;
    }

    /**
     * Sử dụng Nginx Proxy trên VPS
     */
    public function setupNginxProxy(string $vpsIp, string $googleDriveUrl, string $fileId): string
    {
        $nginxConfig = <<<NGINX
# Nginx proxy config cho Google Drive
location /proxy/{$fileId}/ {
    proxy_pass https://drive.google.com/;
    proxy_set_header Host drive.google.com;
    proxy_set_header User-Agent "Mozilla/5.0 (compatible; Nginx-Proxy/1.0)";
    proxy_set_header Referer "https://drive.google.com/";
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    
    # Caching
    proxy_cache gdrive_cache;
    proxy_cache_valid 200 1h;
    proxy_cache_valid 206 10m;
    proxy_cache_key \$scheme\$proxy_host\$request_uri;
    
    # Range requests support
    proxy_set_header Range \$http_range;
    proxy_set_header If-Range \$http_if_range;
    proxy_pass_header Content-Range;
    
    # CORS headers
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, HEAD, OPTIONS";
    add_header Access-Control-Allow-Headers "Range, Content-Type";
}

# Cache zone
proxy_cache_path /var/cache/nginx/gdrive levels=1:2 keys_zone=gdrive_cache:10m max_size=1g inactive=60m use_temp_path=off;
NGINX;

        return "https://{$vpsIp}/proxy/{$fileId}/";
    }

    /**
     * Multi-CDN strategy
     */
    public function getOptimalCDNUrl(string $googleDriveUrl, string $fileId, string $userLocation = 'US'): string
    {
        $cdnProviders = [
            'cloudflare' => $this->createProxyUrl($googleDriveUrl, $fileId),
            'aws_cloudfront' => $this->getCloudFrontUrl($googleDriveUrl, $fileId),
            'nginx_proxy' => $this->getNginxProxyUrl($googleDriveUrl, $fileId),
            'direct' => $googleDriveUrl
        ];

        // Test speed và chọn CDN tốt nhất
        return $this->selectFastestCDN($cdnProviders, $userLocation);
    }

    /**
     * Test tốc độ các CDN
     */
    private function selectFastestCDN(array $cdnProviders, string $userLocation): string
    {
        $cacheKey = "fastest_cdn_{$userLocation}";
        
        return Cache::remember($cacheKey, 300, function () use ($cdnProviders) {
            $speeds = [];
            
            foreach ($cdnProviders as $provider => $url) {
                $startTime = microtime(true);
                
                try {
                    $response = Http::timeout(5)->head($url);
                    $responseTime = microtime(true) - $startTime;
                    
                    if ($response->successful()) {
                        $speeds[$provider] = $responseTime;
                    }
                } catch (\Exception $e) {
                    $speeds[$provider] = 999; // Penalty for failed requests
                }
            }
            
            // Chọn CDN nhanh nhất
            $fastestProvider = array_keys($speeds, min($speeds))[0];
            
            Log::info('CDN speed test results', [
                'location' => $userLocation,
                'speeds' => $speeds,
                'selected' => $fastestProvider
            ]);
            
            return $cdnProviders[$fastestProvider];
        });
    }

    /**
     * Adaptive streaming với multiple CDNs
     */
    public function createAdaptiveStreamingUrls(string $googleDriveUrl, string $fileId): array
    {
        return [
            'primary' => $this->createProxyUrl($googleDriveUrl, $fileId),
            'fallback' => [
                $this->getNginxProxyUrl($googleDriveUrl, $fileId),
                $googleDriveUrl // Direct as last resort
            ],
            'hls_proxy' => $this->createHLSProxyUrl($googleDriveUrl, $fileId)
        ];
    }

    /**
     * Tạo HLS proxy URL
     */
    private function createHLSProxyUrl(string $googleDriveUrl, string $fileId): string
    {
        $baseUrl = config('services.hls_proxy.base_url');
        return "{$baseUrl}/hls/{$fileId}/playlist.m3u8?source=" . urlencode($googleDriveUrl);
    }

    /**
     * Get CloudFront URL
     */
    private function getCloudFrontUrl(string $googleDriveUrl, string $fileId): string
    {
        $distributionDomain = config('services.aws.cloudfront_domain');
        return "https://{$distributionDomain}/proxy?url=" . urlencode($googleDriveUrl) . "&file_id={$fileId}";
    }

    /**
     * Get Nginx proxy URL
     */
    private function getNginxProxyUrl(string $googleDriveUrl, string $fileId): string
    {
        $proxyDomain = config('services.nginx_proxy.domain');
        return "https://{$proxyDomain}/proxy/{$fileId}/";
    }
} 