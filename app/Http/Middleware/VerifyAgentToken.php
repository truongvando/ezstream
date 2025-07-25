<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated This middleware is deprecated. Agent authentication is now handled via Redis.
 * VPS agents authenticate using Redis connection instead of HTTP token authentication.
 */
class VerifyAgentToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agentToken = $request->header('X-Agent-Token');
        $expectedToken = config('services.agent.secret_token');

        if (!$expectedToken) {
            // Log a critical error if the token is not configured on the server
            // This prevents the endpoint from being unintentionally left open.
            \Illuminate\Support\Facades\Log::error('[VerifyAgentToken] AGENT_SECRET_TOKEN is not configured in services.php or .env. Access denied for security.');
            return response()->json(['message' => 'Endpoint misconfiguration.'], 500);
        }

        if (!$agentToken || !hash_equals($expectedToken, $agentToken)) {
            \Illuminate\Support\Facades\Log::warning('[VerifyAgentToken] Invalid or missing token.', [
                'ip' => $request->ip()
            ]);
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
