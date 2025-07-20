<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">

        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Quản Lý Streams
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Tạo và quản lý các stream video của bạn
                </p>
            </div>

            <div class="flex space-x-3">
                {{-- Quick Stream Button --}}
                <button wire:click="openQuickStreamModal"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    🚀 Quick Stream
                </button>

                {{-- Regular Stream Button --}}
                <button wire:click="create"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tạo Stream Mới
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-4 mb-6">
            @if(isset($isAdmin) && $isAdmin && isset($users))
            <div class="flex-1 min-w-48">
                <select wire:model="filterUserId"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Tất cả người dùng</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div class="flex-1 min-w-48">
                <select wire:model="filterStatus" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Tất cả trạng thái</option>
                    <option value="INACTIVE">Không hoạt động</option>
                    <option value="STARTING">Đang khởi động</option>
                    <option value="STREAMING">Đang phát</option>
                    <option value="STOPPING">Đang dừng</option>
                    <option value="ERROR">Lỗi</option>
                </select>
            </div>
        </div>

        {{-- Stream Cards Grid --}}
        @if($streams && $streams->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($streams as $stream)
            <div wire:key="stream-card-{{ $stream->id }}-{{ $stream->status }}-{{ $stream->updated_at }}" class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                
                {{-- Card Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $stream->title }}
                                </h3>
                                @if($stream->is_quick_stream)
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-800 dark:text-purple-100">
                                        ⚡️ Quick
                                    </span>
                                @endif
                            </div>
                            @if($stream->description)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ $stream->description }}
                            </p>
                            @endif
                        </div>
                        
                        {{-- Status Badge --}}
                        <div class="ml-3 flex-shrink-0">
                            @if($stream->status === 'STREAMING')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></div>
                                    Đang phát
                                    @if($stream->last_status_update)
                                        @php
                                            $minutesSinceHeartbeat = $stream->last_status_update->diffInMinutes();
                                        @endphp
                                        @if($minutesSinceHeartbeat < 2)
                                            <span class="ml-1 text-green-500 animate-pulse" title="Heartbeat: {{ $stream->last_status_update->diffForHumans() }}">●</span>
                                        @elseif($minutesSinceHeartbeat < 3)
                                            <span class="ml-1 text-yellow-500" title="Heartbeat cũ: {{ $stream->last_status_update->diffForHumans() }}">●</span>
                                        @else
                                            <span class="ml-1 text-red-500" title="Không có heartbeat: {{ $stream->last_status_update->diffForHumans() }}">●</span>
                                        @endif
                                    @else
                                        <span class="ml-1 text-gray-400" title="Chưa có heartbeat">○</span>
                                    @endif
                                </span>
                            @elseif($stream->status === 'STARTING')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-1 animate-spin"></div>
                                    Đang khởi động
                                </span>
                            @elseif($stream->status === 'STOPPING')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-1 animate-pulse"></div>
                                    Đang dừng
                                </span>
                            @elseif($stream->status === 'ERROR')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <div class="w-2 h-2 bg-red-400 rounded-full mr-1"></div>
                                    Lỗi
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                    <div class="w-2 h-2 bg-gray-400 rounded-full mr-1"></div>
                                    Không hoạt động
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4 space-y-3">
                    
                    {{-- Stream Info Grid --}}
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Người tạo:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $stream->user->name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Nền tảng:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $stream->platform_icon }} {{ $stream->platform }}
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Máy chủ:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $stream->vpsServer ? $stream->vpsServer->name : 'Chưa gán' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Video:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                @php
                                    $fileCount = 0;
                                    if ($stream->video_source_path && is_array($stream->video_source_path)) {
                                        $fileCount = count($stream->video_source_path);
                                    } elseif ($stream->userFile) {
                                        $fileCount = 1;
                                    }
                                @endphp
                                {{ $fileCount }} file{{ $fileCount !== 1 ? 's' : '' }}
                            </p>
                        </div>

                    </div>

                    {{-- Schedule Information (Separate section) --}}
                    @if(($stream->enable_schedule ?? false) && $stream->scheduled_at)
                    <div class="mt-4 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                        <div class="flex items-center space-x-2 mb-2">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium text-purple-700 dark:text-purple-300">📅 Lịch phát</span>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Bắt đầu:</span>
                                <span class="font-medium text-purple-600 dark:text-purple-400">
                                    {{ $stream->scheduled_at->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            @if(isset($stream->scheduled_end) && $stream->scheduled_end)
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Kết thúc:</span>
                                <span class="font-medium text-purple-600 dark:text-purple-400">
                                    {{ $stream->scheduled_end->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            @endif
                            @php
                                $now = now();
                                $scheduledAt = $stream->scheduled_at;
                                $isUpcoming = $scheduledAt->isFuture();
                                $isPast = $scheduledAt->isPast();
                                $timeUntil = $isUpcoming ? $scheduledAt->diffForHumans() : null;
                            @endphp
                            @if($isUpcoming)
                            <div class="mt-2 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    ⏳ Sẽ bắt đầu {{ $timeUntil }}
                                </span>
                            </div>
                            @elseif($isPast && $stream->status === 'INACTIVE')
                            <div class="mt-2 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    ⚠️ Đã quá giờ phát ({{ $scheduledAt->diffForHumans() }})
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Progress Bar (show when STARTING or has active progress) --}}
                @if($stream->status === 'STARTING' || ($stream->status === 'STREAMING' && isset($stream->progress_data)))
                <div class="px-4 pb-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-blue-600 dark:text-blue-400">
                            {{ $stream->progress_data['message'] ?? 'Đang chuẩn bị...' }}
                        </span>
                        <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                            {{ ($stream->progress_data['progress_percentage'] ?? 10) }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                             style="width: {{ ($stream->progress_data['progress_percentage'] ?? 10) }}%"></div>
                    </div>
                    {{-- Download details (if available) --}}
                    @if(isset($stream->progress_data['details']) && !empty($stream->progress_data['details']))
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        @if(isset($stream->progress_data['details']['file_name']))
                            <span>Đang tải {{ $stream->progress_data['details']['file_name'] }}</span>
                            @if(isset($stream->progress_data['details']['downloaded_mb']) && isset($stream->progress_data['details']['total_mb']))
                                <span>: {{ $stream->progress_data['details']['downloaded_mb'] }}MB/{{ $stream->progress_data['details']['total_mb'] }}MB</span>
                            @endif
                        @endif
                    </div>
                    @endif
                </div>
                @endif

                {{-- Card Footer with Actions --}}
                <div class="p-4 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-b-lg">
                    <div class="flex justify-between items-center">
                        {{-- Stream Controls --}}
                        <div class="flex space-x-2">
                            @if($stream->status === 'INACTIVE')
                                <button wire:click="startStream({{ $stream->id }})" wire:loading.attr="disabled" wire:target="startStream({{ $stream->id }})" class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md shadow-sm transition-colors duration-200">
                                    <svg wire:loading.remove wire:target="startStream({{ $stream->id }})" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg wire:loading wire:target="startStream({{ $stream->id }})" class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Bắt đầu Stream
                                </button>
                            @elseif($stream->status === 'STREAMING')
                                <button wire:click="stopStream({{ $stream->id }})" wire:loading.attr="disabled" wire:target="stopStream({{ $stream->id }})" class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-md shadow-sm transition-colors duration-200">
                                    <svg wire:loading.remove wire:target="stopStream({{ $stream->id }})" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                    </svg>
                                    <svg wire:loading wire:target="stopStream({{ $stream->id }})" class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Dừng Stream
                                </button>
                            @elseif($stream->status === 'STARTING' || $stream->status === 'STOPPING')
                                <button disabled class="inline-flex items-center px-3 py-1.5 bg-gray-400 text-white text-xs font-medium rounded-md shadow-sm cursor-not-allowed">
                                    <svg class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ $stream->status === 'STARTING' ? 'Đang khởi động...' : 'Đang dừng...' }}
                                </button>
                            @elseif($stream->status === 'ERROR')
                                <button wire:click="startStream({{ $stream->id }})" wire:loading.attr="disabled" wire:target="startStream({{ $stream->id }})" class="inline-flex items-center px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-medium rounded-md shadow-sm transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Thử lại
                                </button>
                            @endif
                        </div>

                        {{-- Edit & Delete --}}
                        <div class="flex space-x-2">
                            <button wire:click="edit({{ $stream->id }})" class="inline-flex items-center px-2 py-1 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button wire:click="confirmDelete({{ $stream->id }})" class="inline-flex items-center px-2 py-1 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-600 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Chưa có Stream nào</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream mới hoặc sử dụng Quick Stream.</p>
            <div class="mt-6">
                <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tạo Stream Đầu Tiên
                </button>
            </div>
        </div>
        @endif

        {{-- Pagination --}}
        @if($streams && $streams->hasPages())
        <div class="mt-6">
            {{ $streams->links() }}
        </div>
        @endif
    </div>
</div>
