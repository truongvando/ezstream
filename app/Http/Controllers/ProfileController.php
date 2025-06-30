<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\TelegramNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        // Test Telegram connection if both fields are provided
        if (!empty($validated['telegram_bot_token']) && !empty($validated['telegram_chat_id'])) {
            $telegramService = new TelegramNotificationService();
            $testMessage = "🔔 *Test thông báo*\n\nXin chào {$request->user()->name}!\n\nCấu hình Telegram của bạn đã được thiết lập thành công. Bạn sẽ nhận được thông báo về streams và thanh toán tại đây.\n\nThời gian: " . now()->format('d/m/Y H:i:s');
            
            $success = $telegramService->sendMessage(
                $validated['telegram_bot_token'],
                $validated['telegram_chat_id'],
                $testMessage
            );
            
            if (!$success) {
                return Redirect::route('profile.edit')
                    ->withErrors(['telegram_bot_token' => 'Không thể kết nối đến Telegram. Vui lòng kiểm tra lại Bot Token và Chat ID.']);
            }
        }

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        $message = 'profile-updated';
        if (!empty($validated['telegram_bot_token']) && !empty($validated['telegram_chat_id'])) {
            $message = 'profile-updated-telegram-tested';
        }

        return Redirect::route('profile.edit')->with('status', $message);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
