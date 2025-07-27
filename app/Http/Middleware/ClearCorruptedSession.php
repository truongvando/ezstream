<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClearCorruptedSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check on login page
        if ($request->is('login') && $request->isMethod('GET')) {

            // Check if user has corrupted session (logged in but can't access dashboard)
            if (Auth::check()) {
                $user = Auth::user();

                // Check if session is corrupted (user exists but session might be invalid)
                if (!$user || !$request->session()->has('_token')) {
                    Log::warning('Detected corrupted session, clearing...', [
                        'user_id' => $user?->id,
                        'session_id' => $request->session()->getId(),
                        'ip' => $request->ip()
                    ]);

                    // Force clear corrupted session
                    Auth::logout();
                    $request->session()->flush();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    // Redirect to login with message
                    return redirect('/login')->with('status', 'Phiên đăng nhập đã được làm mới. Vui lòng đăng nhập lại.');
                }

                // If user is properly authenticated, redirect to dashboard
                if ($user->isAdmin()) {
                    return redirect()->route('admin.dashboard');
                } else {
                    return redirect()->route('dashboard');
                }
            }
        }

        return $next($request);
    }
}
