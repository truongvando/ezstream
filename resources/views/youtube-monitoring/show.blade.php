<x-sidebar-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="{{ route('youtube.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $channel->channel_name }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Chi tiết kênh YouTube và phân tích hiệu suất
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ $channel->channel_url }}" target="_blank" 
                   class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                    Xem kênh
                </a>
                <button onclick="openAlertSettings()"
                        class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"/>
                    </svg>
                    Cài đặt Alert
                </button>
                <button onclick="toggleChannel({{ $channel->id }})"
                        class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    {{ $channel->is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                </button>
            </div>
        </div>
    </x-slot>

    <!-- Channel Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @php
            $latest = $channel->latestSnapshot();
        @endphp
        
        <!-- Subscribers -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Subscribers</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $latest ? $latest->formatted_subscriber_count : 'N/A' }}
                        </dd>
                        @if($growthMetrics['subscriber_growth'] != 0)
                            <dd class="text-sm {{ $growthMetrics['subscriber_growth'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthMetrics['subscriber_growth'] > 0 ? '+' : '' }}{{ number_format($growthMetrics['subscriber_growth']) }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Videos -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Videos</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $latest ? $latest->formatted_video_count : 'N/A' }}
                        </dd>
                        @if($growthMetrics['video_growth'] != 0)
                            <dd class="text-sm {{ $growthMetrics['video_growth'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthMetrics['video_growth'] > 0 ? '+' : '' }}{{ $growthMetrics['video_growth'] }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Views -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Views</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $latest ? $latest->formatted_view_count : 'N/A' }}
                        </dd>
                        @if($growthMetrics['view_growth'] != 0)
                            <dd class="text-sm {{ $growthMetrics['view_growth'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthMetrics['view_growth'] > 0 ? '+' : '' }}{{ number_format($growthMetrics['view_growth']) }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Growth Rate -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-md flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Growth Rate</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            @if($growthMetrics['growth_rate'] != 0)
                                <span class="{{ $growthMetrics['growth_rate'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $growthMetrics['growth_rate'] > 0 ? '+' : '' }}{{ number_format($growthMetrics['growth_rate'], 2) }}%
                                </span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">-</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Info -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Thông tin kênh</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex items-start space-x-4">
                <img class="h-20 w-20 rounded-full object-cover" 
                     src="{{ $channel->thumbnail_url ?: 'https://via.placeholder.com/80x80?text=YT' }}" 
                     alt="{{ $channel->channel_name }}">
                <div class="flex-1">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $channel->channel_name }}</h4>
                    @if($channel->channel_handle)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $channel->channel_handle }}</p>
                    @endif
                    @if($channel->country)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $channel->country }}</p>
                    @endif
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tạo kênh: {{ $channel->channel_created_at ? $channel->channel_created_at->format('d/m/Y') : 'N/A' }}
                    </p>
                </div>
            </div>
            <div>
                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Mô tả kênh</h5>
                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-4">
                    {{ $channel->description ?: 'Không có mô tả' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Biểu đồ tăng trưởng (30 ngày gần nhất)</h3>
        <div class="h-64">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Recent Videos -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Video gần đây</h3>
        </div>
        @if($channel->videos->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Video</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Views</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Likes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comments</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày đăng</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($channel->videos->take(20) as $video)
                            @php
                                $videoSnapshot = $video->snapshots->first();
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-16 w-24">
                                            <img class="h-16 w-24 rounded object-cover" 
                                                 src="{{ $video->thumbnail_url ?: 'https://via.placeholder.com/120x90?text=Video' }}" 
                                                 alt="{{ $video->title }}">
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">
                                                <a href="{{ $video->youtube_url }}" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">
                                                    {{ $video->title }}
                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $video->formatted_duration }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $videoSnapshot ? $videoSnapshot->formatted_view_count : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $videoSnapshot ? $videoSnapshot->formatted_like_count : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $videoSnapshot ? $videoSnapshot->formatted_comment_count : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $video->published_at->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                        {{ $video->status === 'live' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                                        {{ ucfirst($video->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có video nào</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Video sẽ được cập nhật trong lần đồng bộ tiếp theo.</p>
            </div>
        @endif
    </div>

    <!-- Alert Settings Modal -->
    <div id="alertSettingsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cài đặt Alert cho {{ $channel->channel_name }}</h3>
                    <button onclick="closeAlertSettings()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form id="alertSettingsForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- New Video Alert -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🎬</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Video mới</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi có video mới</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[new_video][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Subscriber Milestone -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🎯</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Mốc Subscribers</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi đạt mốc subscribers</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[subscriber_milestone][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Growth Spike -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">📈</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Tăng trưởng đột biến</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi tăng trưởng bất thường</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[growth_spike][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ngưỡng tăng trưởng (%)</label>
                                <input type="number" name="settings[growth_spike][threshold]" min="1" max="100"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white">
                            </div>
                        </div>

                        <!-- Video Viral -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">🔥</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Video viral</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi video viral</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[video_viral][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ngưỡng views viral</label>
                                <input type="number" name="settings[video_viral][view_threshold]" min="1000" step="1000"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white">
                            </div>
                        </div>

                        <!-- View Milestone -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">👀</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Mốc Views</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi đạt mốc views</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[view_milestone][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>

                        <!-- Channel Inactive -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3">😴</span>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Kênh không hoạt động</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Thông báo khi kênh ngừng đăng video</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[channel_inactive][enabled]" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Số ngày không hoạt động</label>
                                <input type="number" name="settings[channel_inactive][threshold]" min="1" max="365"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <button type="button" onclick="closeAlertSettings()"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                            Hủy
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors duration-200">
                            Lưu cài đặt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Growth Chart
        const ctx = document.getElementById('growthChart').getContext('2d');
        const chartData = @json($chartData);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.dates,
                datasets: [
                    {
                        label: 'Subscribers',
                        data: chartData.subscribers,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Videos',
                        data: chartData.videos,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#374151',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat().format(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Thời gian',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.2)'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Subscribers',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#ef4444'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.2)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat().format(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Videos',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#3b82f6'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return Math.round(value);
                            }
                        }
                    }
                }
            }
        });

        // Alert Settings functions
        let currentAlertSettings = {};

        async function openAlertSettings() {
            try {
                // Load current settings
                const response = await fetch(`/youtube-monitoring/{{ $channel->id }}/alert-settings`, {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                currentAlertSettings = data.settings;

                // Populate form with current settings
                populateAlertForm(currentAlertSettings);

                // Show modal
                document.getElementById('alertSettingsModal').classList.remove('hidden');
            } catch (error) {
                alert('Có lỗi khi tải cài đặt alert');
            }
        }

        function closeAlertSettings() {
            document.getElementById('alertSettingsModal').classList.add('hidden');
        }

        function populateAlertForm(settings) {
            const form = document.getElementById('alertSettingsForm');

            // Populate each alert type
            Object.keys(settings).forEach(alertType => {
                const alertSettings = settings[alertType];

                // Set enabled checkbox
                const enabledCheckbox = form.querySelector(`input[name="settings[${alertType}][enabled]"]`);
                if (enabledCheckbox) {
                    enabledCheckbox.checked = alertSettings.enabled || false;
                }

                // Set threshold values
                if (alertSettings.threshold !== undefined) {
                    const thresholdInput = form.querySelector(`input[name="settings[${alertType}][threshold]"]`);
                    if (thresholdInput) {
                        thresholdInput.value = alertSettings.threshold;
                    }
                }

                if (alertSettings.view_threshold !== undefined) {
                    const viewThresholdInput = form.querySelector(`input[name="settings[${alertType}][view_threshold]"]`);
                    if (viewThresholdInput) {
                        viewThresholdInput.value = alertSettings.view_threshold;
                    }
                }
            });
        }

        // Alert settings form submission
        document.getElementById('alertSettingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const settings = {};

            // Parse form data into settings object
            for (let [key, value] of formData.entries()) {
                const matches = key.match(/settings\[([^\]]+)\]\[([^\]]+)\]/);
                if (matches) {
                    const [, alertType, settingKey] = matches;

                    if (!settings[alertType]) {
                        settings[alertType] = {};
                    }

                    // Convert values to appropriate types
                    if (settingKey === 'enabled') {
                        settings[alertType][settingKey] = true; // Checkbox is checked
                    } else if (settingKey === 'threshold' || settingKey === 'view_threshold') {
                        settings[alertType][settingKey] = parseInt(value);
                    } else {
                        settings[alertType][settingKey] = value;
                    }
                }
            }

            // Set enabled = false for unchecked checkboxes
            Object.keys(currentAlertSettings).forEach(alertType => {
                if (!settings[alertType] || settings[alertType].enabled === undefined) {
                    if (!settings[alertType]) settings[alertType] = {};
                    settings[alertType].enabled = false;
                }

                // Preserve existing settings not in form
                settings[alertType] = { ...currentAlertSettings[alertType], ...settings[alertType] };
            });

            try {
                const response = await fetch(`/youtube-monitoring/{{ $channel->id }}/alert-settings`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ settings })
                });

                const data = await response.json();

                if (data.success) {
                    closeAlertSettings();
                    alert('Cài đặt alert đã được lưu thành công!');
                } else {
                    alert(data.message || 'Có lỗi xảy ra khi lưu cài đặt');
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi lưu cài đặt');
            }
        });

        // Toggle channel function
        async function toggleChannel(channelId) {
            if (!confirm('Bạn có chắc muốn thay đổi trạng thái kênh này?')) return;

            try {
                const response = await fetch(`/youtube-monitoring/${channelId}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Có lỗi xảy ra');
            }
        }

        // Close modal when clicking outside
        document.getElementById('alertSettingsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAlertSettings();
            }
        });
    </script>
    @endpush
</x-sidebar-layout>
