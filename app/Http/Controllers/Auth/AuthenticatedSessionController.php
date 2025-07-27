<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Clear any existing session data before login
        $request->session()->flush();
        $request->session()->regenerate();

        $request->authenticate();

        // Force regenerate session after successful login
        $request->session()->regenerate();
        $request->session()->migrate(true);

        // Log successful login
        \Log::info('User logged in successfully', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'session_id' => $request->session()->getId(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Redirect admin to admin dashboard
        if (Auth::user()->isAdmin()) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Force logout from all guards
        Auth::guard('web')->logout();

        // Clear all session data
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Force regenerate session ID
        $request->session()->migrate(true);

        // Clear remember token if exists
        if ($user = Auth::user()) {
            $user->remember_token = null;
            $user->save();
        }

        // Create response with cache control headers
        $response = redirect('/login')->with('status', 'Đã đăng xuất thành công');

        // Force browser to not cache this response
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Clear session cookie explicitly
        $response->withCookie(cookie()->forget(config('session.cookie')));

        return $response;
    }
}
