<!-- Stream Management Cards -->
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">

        <!-- Header -->
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
                <!-- Quick Stream Button -->
                <button wire:click="openQuickStreamModal"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    🚀 Quick Stream
                </button>

                <!-- Regular Stream Button -->
                <button wire:click="create"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tạo Stream Mới
                </button>
            </div>
        </div>

        <!-- Filters -->
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

        <!-- Stream Cards Grid -->
        @if($streams && $streams->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($streams as $stream)
            <div wire:key="stream-card-{{ $stream->id }}-{{ $stream->status }}-{{ $stream->updated_at }}" class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                
                <!-- Card Header -->
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
                        
                        <!-- Status Badge -->
                        <div class="ml-3 flex-shrink-0">
                            @if($stream->status === 'STREAMING')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></div>
                                    Đang phát
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

                <!-- Card Body -->
                <div class="p-4 space-y-3">
                    
                    <!-- Stream Info Grid -->
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

                    <!-- Progress Bar (show when STARTING or has active progress) -->
                    @if($stream->status === 'STARTING' || ($stream->status === 'STREAMING' && isset($stream->progress_data)))
                    <div class="space-y-2">
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
                        <!-- Download details (if available) -->
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

                    <!-- Error Message -->
                    @if($stream->error_message)
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $stream->error_message }}</p>
                    </div>
                    @endif

                    <!-- Timestamps -->
                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                        <div>Tạo: {{ $stream->created_at->format('d/m/Y H:i') }}</div>
                        @if($stream->last_started_at)
                        <div>Khởi động: {{ $stream->last_started_at->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>
                </div>

                <!-- Card Footer - Action Buttons -->
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600 rounded-b-lg">
                    <div class="flex items-center justify-between space-x-3">
                        
                        <!-- Main Action Button -->
                        <div class="flex-1">
                            @if($stream->status === 'STREAMING')
                                <button wire:click="stopStream({{ $stream->id }})" 
                                        wire:loading.attr="disabled"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border-2 border-red-600 text-sm font-semibold rounded-lg text-red-600 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"></path>
                                    </svg>
                                    Dừng Stream
                                </button>
                            @elseif($stream->status === 'STARTING')
                                <div class="w-full space-y-2">
                                    <div class="inline-flex items-center justify-center px-4 py-2 border-2 border-blue-600 text-sm font-semibold rounded-lg text-blue-600 bg-blue-50 w-full">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                                        <span id="progress-text-{{ $stream->id }}">Đang chuẩn bị...</span>
                                    </div>
                                    <button wire:click="stopStream({{ $stream }})"
                                            wire:loading.attr="disabled"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 border border-red-300 text-sm font-medium rounded-lg text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Hủy
                                    </button>
                                </div>
                            @elseif(in_array($stream->status, ['PENDING', 'INACTIVE', 'STOPPED', 'ERROR']))
                                <button wire:click="startStream({{ $stream }})"
                                        wire:loading.attr="disabled"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 border-2 border-green-600 text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-700 hover:border-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m2-10v18a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2h8l4 4z"></path>
                                    </svg>
                                    Bắt đầu Stream
                                </button>
                            @elseif($stream->status === 'STOPPING')
                                <div class="w-full inline-flex items-center justify-center px-4 py-2 border-2 border-orange-600 text-sm font-semibold rounded-lg text-orange-600 bg-orange-50 cursor-not-allowed">
                                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-600 mr-2"></div>
                                    Đang dừng...
                                </div>
                            @endif
                        </div>

                        <!-- Secondary Actions -->
                        <div class="flex items-center space-x-2">
                            <!-- Edit Button -->
                            <button wire:click="edit({{ $stream->id }})" 
                                    class="inline-flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>

                            <!-- Delete Button -->
                            <button wire:click="confirmDelete({{ $stream->id }})" 
                                    class="inline-flex items-center p-2 border border-gray-300 dark:border-gray-600 rounded-lg text-red-500 hover:text-red-700 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($streams && $streams->hasPages())
        <div class="mt-6">
            {{ $streams->links() }}
        </div>
        @endif

        @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có stream nào</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream đầu tiên của bạn.</p>
            @if(!isset($isAdmin) || $isAdmin !== true)
            <div class="mt-6">
                <button wire:click="create" 
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Tạo Stream Mới
                </button>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>

<!-- Progress now handled by Livewire polling (wire:poll.2s) -->
