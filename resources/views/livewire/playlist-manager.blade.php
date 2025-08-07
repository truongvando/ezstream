<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Quản lý Playlist
            </h3>
            <div class="flex items-center space-x-2">
                @if($stream->status === 'STREAMING')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <svg class="w-2 h-2 mr-1 fill-current" viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3"/>
                        </svg>
                        Đang phát
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        Không hoạt động
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
        <div class="flex flex-wrap items-center gap-4">
            <!-- Loop Toggle -->
            <div class="flex items-center">
                <input type="checkbox" 
                       wire:click="toggleLoopMode" 
                       @if($stream->loop) checked @endif
                       @if($stream->status !== 'STREAMING') disabled @endif
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    Loop 24/7
                </label>
            </div>

            <!-- Playback Order -->
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-700 dark:text-gray-300">Thứ tự:</label>
                <select wire:change="setPlaybackOrder($event.target.value)" 
                        @if($stream->status !== 'STREAMING') disabled @endif
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md">
                    <option value="sequential" @if($stream->playlist_order === 'sequential') selected @endif>Tuần tự</option>
                    <option value="random" @if($stream->playlist_order === 'random') selected @endif>Ngẫu nhiên</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center space-x-2 ml-auto">
                <button wire:click="openAddModal" 
                        @if($stream->status !== 'STREAMING' || empty($availableFiles)) disabled @endif
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Thêm video
                </button>
                
                <button wire:click="openDeleteModal" 
                        @if($stream->status !== 'STREAMING' || count($currentFiles) <= 1) disabled @endif
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Xóa video
                </button>

                <button wire:click="getPlaylistStatus" 
                        @if($stream->status !== 'STREAMING') disabled @endif
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Trạng thái
                </button>
            </div>
        </div>
    </div>

    <!-- Current Playlist -->
    <div class="px-6 py-4">
        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
            Playlist hiện tại ({{ count($currentFiles) }} video)
        </h4>
        
        @if(empty($currentFiles))
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p>Chưa có video nào trong playlist</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($currentFiles as $index => $file)
                <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex-shrink-0 w-8 text-center">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $index + 1 }}</span>
                    </div>
                    
                    <div class="flex-1 min-w-0 ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ $file['original_name'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($file['size'] / 1024 / 1024, 1) }} MB
                            @if($file['disk'] === 'bunny_stream')
                                • Stream Library
                            @endif
                        </p>
                    </div>

                    @if($stream->status === 'STREAMING' && $stream->playlist_order === 'sequential')
                    <div class="flex items-center space-x-1 ml-3">
                        <button wire:click="moveUp({{ $index }})" 
                                @if($index === 0) disabled @endif
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                            </svg>
                        </button>
                        <button wire:click="moveDown({{ $index }})" 
                                @if($index === count($currentFiles) - 1) disabled @endif
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Add Videos Modal -->
    @if($showAddModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="closeAddModal"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Thêm video vào playlist</h3>
                </div>
                <div class="px-6 py-4 max-h-96 overflow-y-auto">
                    @if(empty($availableFiles))
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                            Không có video nào khả dụng để thêm
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach($availableFiles as $file)
                            <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                <input type="checkbox" 
                                       wire:model="selectedNewFiles" 
                                       value="{{ $file['id'] }}"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <div class="ml-3 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file['original_name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($file['size'] / 1024 / 1024, 1) }} MB</p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <button wire:click="closeAddModal" 
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Hủy
                    </button>
                    <button wire:click="addVideos" 
                            class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Thêm video
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Delete Videos Modal -->
    @if($showDeleteModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="closeDeleteModal"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Xóa video khỏi playlist</h3>
                </div>
                <div class="px-6 py-4 max-h-96 overflow-y-auto">
                    <div class="space-y-2">
                        @foreach($currentFiles as $file)
                        <label class="flex items-center p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                            <input type="checkbox" 
                                   wire:model="selectedDeleteFiles" 
                                   value="{{ $file['id'] }}"
                                   class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file['original_name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($file['size'] / 1024 / 1024, 1) }} MB</p>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <button wire:click="closeDeleteModal" 
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Hủy
                    </button>
                    <button wire:click="deleteVideos" 
                            class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                        Xóa video
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
