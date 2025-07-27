<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Add any routes that should be excluded from CSRF verification
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Debug CSRF issues in development
        if (config('app.debug') && $request->isMethod('POST')) {
            $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
            $sessionToken = $request->session()->token();

            Log::debug('CSRF Debug', [
                'url' => $request->url(),
                'method' => $request->method(),
                'has_token' => !empty($token),
                'token_length' => $token ? strlen($token) : 0,
                'session_token_length' => $sessionToken ? strlen($sessionToken) : 0,
                'tokens_match' => $token === $sessionToken,
                'session_id' => $request->session()->getId(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return parent::handle($request, $next);
    }
}
