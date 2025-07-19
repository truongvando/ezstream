<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            Log::info('Password reset request started', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $request->validate([
                'email' => ['required', 'email'],
            ]);

            Log::info('Validation passed, attempting to send reset link', [
                'email' => $request->email
            ]);

            // Check if user exists
            $user = \App\Models\User::where('email', $request->email)->first();
            if (!$user) {
                Log::warning('Password reset attempted for non-existent email', [
                    'email' => $request->email
                ]);
            } else {
                Log::info('User found for password reset', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            // Check mail configuration
            Log::info('Mail configuration check', [
                'mail_mailer' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
                'mail_port' => config('mail.mailers.smtp.port'),
                'mail_from_address' => config('mail.from.address'),
                'mail_from_name' => config('mail.from.name')
            ]);

            // We will send the password reset link to this user. Once we have attempted
            // to send the link, we will examine the response then see the message we
            // need to show to the user. Finally, we'll send out a proper response.
            $status = Password::sendResetLink(
                $request->only('email')
            );

            Log::info('Password reset link send attempt completed', [
                'email' => $request->email,
                'status' => $status,
                'is_sent' => $status == Password::RESET_LINK_SENT
            ]);

            return $status == Password::RESET_LINK_SENT
                        ? back()->with('status', __($status))
                        : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);

        } catch (\Exception $e) {
            Log::error('Password reset failed with exception', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Có lỗi xảy ra khi gửi email đặt lại mật khẩu. Vui lòng thử lại sau.']);
        }
    }
}
