<?php

namespace App\Http\Controllers;

use App\Models\YoutubeAlert;
use App\Models\YoutubeAlertSetting;
use App\Models\YoutubeChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class YoutubeAlertController extends Controller
{
    /**
     * Get alerts for current user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = YoutubeAlert::with(['channel'])
            ->forUser($user->id)
            ->orderBy('triggered_at', 'desc');

        // Filter by read status
        if ($request->filled('unread_only')) {
            $query->unread();
        }

        // Filter by channel
        if ($request->filled('channel_id')) {
            $query->forChannel($request->get('channel_id'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        $alerts = $query->paginate(20);

        // Get channels for filter
        $channels = $user->youtubeChannels()->active()->get();

        return response()->json([
            'alerts' => $alerts,
            'channels' => $channels,
            'unread_count' => YoutubeAlert::forUser($user->id)->unread()->count()
        ]);
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(YoutubeAlert $alert)
    {
        $user = Auth::user();
        
        if ($alert->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $alert->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Alert marked as read'
        ]);
    }

    /**
     * Mark all alerts as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        
        YoutubeAlert::forUser($user->id)
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All alerts marked as read'
        ]);
    }

    /**
     * Delete alert
     */
    public function destroy(YoutubeAlert $alert)
    {
        $user = Auth::user();
        
        if ($alert->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert deleted'
        ]);
    }

    /**
     * Get alert settings for a channel
     */
    public function getSettings(YoutubeChannel $channel)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $settings = YoutubeAlertSetting::getForUserAndChannel($user->id, $channel->id);

        return response()->json([
            'settings' => $settings->settings,
            'defaults' => YoutubeAlertSetting::getDefaultSettings()
        ]);
    }

    /**
     * Update alert settings for a channel
     */
    public function updateSettings(Request $request, YoutubeChannel $channel)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*.enabled' => 'boolean',
        ]);

        $settings = YoutubeAlertSetting::getForUserAndChannel($user->id, $channel->id);
        $settings->update(['settings' => $request->get('settings')]);

        return response()->json([
            'success' => true,
            'message' => 'Alert settings updated',
            'settings' => $settings->settings
        ]);
    }

    /**
     * Get unread alerts count
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        
        $count = YoutubeAlert::forUser($user->id)->unread()->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Get recent alerts for notification dropdown
     */
    public function getRecent()
    {
        $user = Auth::user();
        
        $alerts = YoutubeAlert::with(['channel'])
            ->forUser($user->id)
            ->orderBy('triggered_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'alerts' => $alerts,
            'unread_count' => YoutubeAlert::forUser($user->id)->unread()->count()
        ]);
    }
}
