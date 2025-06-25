<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecureUploadMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userIp = $request->ip();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Rate limiting per user
        $userKey = 'upload_rate_limit_user_' . $user->id;
        $userRequests = Cache::get($userKey, 0);
        
        // Max 100 requests per hour per user
        if ($userRequests >= 100) {
            Log::warning('Upload rate limit exceeded for user', [
                'user_id' => $user->id,
                'ip' => $userIp,
                'requests' => $userRequests
            ]);
            return response()->json(['error' => 'Rate limit exceeded. Please try again later.'], 429);
        }
        
        // Rate limiting per IP
        $ipKey = 'upload_rate_limit_ip_' . $userIp;
        $ipRequests = Cache::get($ipKey, 0);
        
        // Max 200 requests per hour per IP
        if ($ipRequests >= 200) {
            Log::warning('Upload rate limit exceeded for IP', [
                'user_id' => $user->id,
                'ip' => $userIp,
                'requests' => $ipRequests
            ]);
            return response()->json(['error' => 'Rate limit exceeded. Please try again later.'], 429);
        }

        // Check for suspicious patterns
        if ($this->isSuspiciousRequest($request)) {
            Log::alert('Suspicious upload request detected', [
                'user_id' => $user->id,
                'ip' => $userIp,
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'data' => $request->all()
            ]);
            return response()->json(['error' => 'Request blocked for security reasons'], 403);
        }

        // Increment counters
        Cache::put($userKey, $userRequests + 1, 3600); // 1 hour
        Cache::put($ipKey, $ipRequests + 1, 3600); // 1 hour

        return $next($request);
    }

    /**
     * Check for suspicious request patterns
     */
    private function isSuspiciousRequest(Request $request): bool
    {
        // Check User-Agent
        $userAgent = $request->userAgent();
        $suspiciousUAs = [
            'curl', 'wget', 'python', 'bot', 'crawler', 'spider', 'scraper'
        ];
        
        foreach ($suspiciousUAs as $ua) {
            if (stripos($userAgent, $ua) !== false) {
                return true;
            }
        }

        // Check for automated requests (missing common headers)
        if (!$request->hasHeader('Accept') || !$request->hasHeader('Accept-Language')) {
            return true;
        }

        // Check for suspicious file names in init request
        if ($request->has('fileName')) {
            $fileName = $request->input('fileName');
            $suspiciousPatterns = [
                '/\.\.(\/|\\\\)/', // Directory traversal
                '/\.(php|js|html|exe|bat|cmd|sh|py)$/i', // Executable extensions
                '/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\.|$)/i', // Windows reserved names
                '/<script|javascript:|vbscript:|onload=|onerror=/i', // Script injection
            ];
            
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $fileName)) {
                    return true;
                }
            }
        }

        // Check for unrealistic file sizes
        if ($request->has('fileSize')) {
            $fileSize = $request->input('fileSize');
            
            // Suspiciously large (>100GB) or small (<1KB for video)
            if ($fileSize > 107374182400 || $fileSize < 1024) {
                return true;
            }
        }

        // Check for too many chunks
        if ($request->has('totalChunks')) {
            $totalChunks = $request->input('totalChunks');
            
            // More than 20,000 chunks is suspicious
            if ($totalChunks > 20000 || $totalChunks < 1) {
                return true;
            }
        }

        return false;
    }
}
