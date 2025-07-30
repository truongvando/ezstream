<?php

namespace App\Http\Controllers;

use App\Models\YoutubeChannel;
use App\Services\YoutubeApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class YoutubeMonitoringController extends Controller
{
    private YoutubeApiService $youtubeApi;

    public function __construct(YoutubeApiService $youtubeApi)
    {
        $this->youtubeApi = $youtubeApi;
    }

    /**
     * Display the main dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Build query based on user role
        $query = YoutubeChannel::with(['snapshots' => function($query) {
                $query->latest('snapshot_date')->limit(2);
            }, 'user'])
            ->active()
            ->orderBy('created_at', 'desc');

        // Non-admin users only see their own channels
        if (!$user->hasRole('admin')) {
            $query->forUser($user->id);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('channel_name', 'like', "%{$search}%")
                  ->orWhere('channel_handle', 'like', "%{$search}%")
                  ->orWhere('channel_id', 'like', "%{$search}%");
            });
        }

        // Filter by user (admin only)
        if ($user->hasRole('admin') && $request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $channels = $query->paginate(20);

        // Get users for filter dropdown (admin only)
        $users = $user->hasRole('admin') 
            ? \App\Models\User::whereHas('youtubeChannels')->get()
            : collect();

        return view('youtube-monitoring.index', compact('channels', 'users'));
    }

    /**
     * Show channel details
     */
    public function show(YoutubeChannel $channel)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this channel');
        }

        // Load relationships
        $channel->load([
            'snapshots' => function ($query) {
                $query->orderBy('snapshot_date', 'desc')->limit(30);
            },
            'videos' => function ($query) {
                $query->with(['snapshots' => function($q) {
                    $q->latest('snapshot_date')->limit(2);
                }])
                ->orderBy('published_at', 'desc')
                ->limit(50);
            }
        ]);

        // Get growth metrics
        $growthMetrics = $channel->getGrowthMetrics();

        // Get chart data for last 30 days
        $chartData = $this->getChartData($channel);

        return view('youtube-monitoring.show', compact('channel', 'growthMetrics', 'chartData'));
    }

    /**
     * Add new channel
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel_url' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $channelUrl = trim($request->get('channel_url'));
            
            // Extract channel ID from URL
            $channelId = $this->youtubeApi->extractChannelId($channelUrl);

            if (!$channelId) {
                Log::warning('Failed to extract channel ID', [
                    'url' => $channelUrl,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Không thể tìm thấy kênh YouTube với URL: {$channelUrl}. Vui lòng kiểm tra lại URL hoặc thử với Channel ID."
                ], 400);
            }

            // Check if channel already exists
            $existingChannel = YoutubeChannel::where('channel_id', $channelId)->first();
            
            if ($existingChannel) {
                // Check if user already has this channel
                if ($existingChannel->user_id === Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are already monitoring this channel'
                    ], 400);
                }
                
                // For admin, show who owns it
                if (Auth::user()->hasRole('admin')) {
                    return response()->json([
                        'success' => false,
                        'message' => "Channel already monitored by {$existingChannel->user->name}"
                    ], 400);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'This channel is already being monitored'
                ], 400);
            }

            // Get channel info from YouTube API
            $channelInfo = $this->youtubeApi->getChannelInfo($channelId);
            
            if (!$channelInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel not found or API error'
                ], 404);
            }

            // Create channel record
            $channel = YoutubeChannel::create([
                'user_id' => Auth::id(),
                'channel_id' => $channelInfo['channel_id'],
                'channel_name' => $channelInfo['channel_name'],
                'channel_url' => $channelInfo['channel_url'],
                'channel_handle' => $channelInfo['channel_handle'],
                'description' => $channelInfo['description'],
                'thumbnail_url' => $channelInfo['thumbnail_url'],
                'country' => $channelInfo['country'],
                'channel_created_at' => $channelInfo['channel_created_at'],
                'last_synced_at' => now(),
            ]);

            // Create initial snapshot
            $channel->snapshots()->create([
                'subscriber_count' => $channelInfo['subscriber_count'],
                'video_count' => $channelInfo['video_count'],
                'view_count' => $channelInfo['view_count'],
                'comment_count' => 0, // Will be updated later
                'snapshot_date' => now(),
            ]);

            Log::info('YouTube channel added', [
                'user_id' => Auth::id(),
                'channel_id' => $channelId,
                'channel_name' => $channelInfo['channel_name']
            ]);

            // Chiến lược sync video thông minh
            try {
                Log::info('Starting smart video sync for new channel', ['channel_id' => $channel->id]);

                $youtubeApi = new \App\Services\YoutubeApiService();

                // Bước 1: Lấy 50 video mới nhất ngay lập tức (để hiển thị)
                $immediateVideoData = $youtubeApi->getChannelVideos($channel->channel_id, 50);
                $syncedCount = 0;

                if (!empty($immediateVideoData['videos'])) {
                    $videoIds = array_column($immediateVideoData['videos'], 'video_id');
                    $videoDetails = $youtubeApi->getVideoDetailsForAI($videoIds);

                    foreach ($videoDetails as $videoDetail) {
                        $video = \App\Models\YoutubeVideo::updateOrCreate(
                            [
                                'video_id' => $videoDetail['video_id'],
                                'youtube_channel_id' => $channel->id
                            ],
                            [
                                'title' => $videoDetail['title'],
                                'description' => $videoDetail['description'],
                                'thumbnail_url' => $videoDetail['thumbnail_url'],
                                'published_at' => $videoDetail['published_at'],
                                'duration' => $videoDetail['duration'] ?? null,
                                'duration_seconds' => $videoDetail['duration_seconds'] ?? 0,
                                'status' => 'active'
                            ]
                        );

                        // Tạo snapshot cho video
                        \App\Models\YoutubeVideoSnapshot::updateOrCreate(
                            [
                                'youtube_video_id' => $video->id,
                                'snapshot_date' => now()->toDateString()
                            ],
                            [
                                'view_count' => $videoDetail['view_count'],
                                'like_count' => $videoDetail['like_count'],
                                'comment_count' => $videoDetail['comment_count']
                            ]
                        );
                        $syncedCount++;
                    }
                }

                // Bước 2: Dispatch background job để lấy toàn bộ video còn lại
                if (!empty($immediateVideoData['nextPageToken']) || $syncedCount >= 50) {
                    \App\Jobs\SyncAllChannelVideosJob::dispatch($channel, $immediateVideoData['nextPageToken'] ?? null)
                        ->delay(now()->addSeconds(10));

                    Log::info('Background full sync job dispatched', [
                        'channel_id' => $channel->id,
                        'immediate_synced' => $syncedCount,
                        'has_more' => !empty($immediateVideoData['nextPageToken'])
                    ]);
                }

                Log::info('Immediate video sync completed', [
                    'channel_id' => $channel->id,
                    'videos_synced' => $syncedCount,
                    'background_job_queued' => !empty($immediateVideoData['nextPageToken']) || $syncedCount >= 50
                ]);

            } catch (\Exception $e) {
                Log::error('Smart video sync failed', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage()
                ]);
                // Fallback: chỉ queue background job
                \App\Jobs\SyncYoutubeVideosJob::dispatch($channel, 50)->delay(now()->addSeconds(5));
            }

            return response()->json([
                'success' => true,
                'message' => 'Kênh đã được thêm và đồng bộ video thành công!',
                'channel' => $channel->load([
                    'snapshots' => function($query) {
                        $query->latest('snapshot_date')->limit(2);
                    },
                    'videos' => function($query) {
                        $query->with('snapshots')->latest('published_at')->limit(5);
                    }
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding YouTube channel', [
                'user_id' => Auth::id(),
                'channel_url' => $channelUrl,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error adding channel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove channel from monitoring
     */
    public function destroy(YoutubeChannel $channel)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            abort(403, 'Unauthorized to delete this channel');
        }

        try {
            $channelName = $channel->channel_name;
            $channel->delete();

            Log::info('YouTube channel removed', [
                'user_id' => $user->id,
                'channel_id' => $channel->channel_id,
                'channel_name' => $channelName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Channel removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing YouTube channel', [
                'user_id' => $user->id,
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error removing channel'
            ], 500);
        }
    }

    /**
     * Get channel videos with pagination
     */
    public function getChannelVideos(YoutubeChannel $channel, Request $request)
    {
        $user = Auth::user();

        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $videos = $channel->videos()
            ->with(['snapshots' => function($query) {
                $query->latest('snapshot_date')->limit(1);
            }])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'videos' => $videos->items(),
            'pagination' => [
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
                'has_more' => $videos->hasMorePages()
            ]
        ]);
    }

    /**
     * Sync more videos for a channel
     */
    public function syncMoreVideos(YoutubeChannel $channel, Request $request)
    {
        $user = Auth::user();

        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $batchSize = $request->get('batch_size', 50);
            $batchSize = min($batchSize, 50); // Giới hạn tối đa 50

            // Dispatch job để sync thêm videos
            \App\Jobs\SyncAllChannelVideosJob::dispatch($channel, null, 5) // Tối đa 5 pages = 250 videos
                ->delay(now()->addSeconds(2));

            Log::info('Manual video sync requested', [
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'batch_size' => $batchSize
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đang đồng bộ thêm videos... Vui lòng chờ vài phút và refresh trang.',
                'estimated_time' => '2-5 phút'
            ]);

        } catch (\Exception $e) {
            Log::error('Manual video sync failed', [
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi đồng bộ videos'
            ], 500);
        }
    }

    /**
     * Toggle channel active status
     */
    public function toggleActive(YoutubeChannel $channel)
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->hasRole('admin') && $channel->user_id !== $user->id) {
            abort(403, 'Unauthorized to modify this channel');
        }

        try {
            $channel->update(['is_active' => !$channel->is_active]);

            return response()->json([
                'success' => true,
                'message' => $channel->is_active ? 'Channel activated' : 'Channel deactivated',
                'is_active' => $channel->is_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating channel status'
            ], 500);
        }
    }

    /**
     * Get chart data for channel analytics
     */
    private function getChartData(YoutubeChannel $channel): array
    {
        $snapshots = $channel->snapshots()
            ->orderBy('snapshot_date', 'asc')
            ->limit(30)
            ->get();

        return [
            'dates' => $snapshots->pluck('snapshot_date')->map(function ($date) {
                return $date->format('M d');
            })->toArray(),
            'subscribers' => $snapshots->pluck('subscriber_count')->toArray(),
            'videos' => $snapshots->pluck('video_count')->toArray(),
            'views' => $snapshots->pluck('view_count')->toArray(),
        ];
    }
}
