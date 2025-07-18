<!-- Unified Stream Form Modal - Refactored for proper scrolling -->
<x-modal-v2 wire:model.live="showCreateModal" max-width="2xl">
    <div class="flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 flex-shrink-0">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ $editingStream ? 'Chỉnh Sửa Stream' : 'Tạo Stream Mới' }}
            </h2>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="flex-grow overflow-y-auto px-6">
            <form id="create-stream-form" wire:submit.prevent="{{ $editingStream ? 'update' : 'store' }}" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <x-input-label for="title" value="Tên Stream" />
                        <x-text-input wire:model.defer="title" id="title" type="text" class="mt-1 block w-full" placeholder="Nhập tên stream" />
                        @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <x-input-label for="description" value="Mô tả (tùy chọn)" />
                        <textarea wire:model.defer="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" placeholder="Mô tả ngắn về stream của bạn"></textarea>
                        @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- File Selection -->
                <div>
                    <x-input-label value="Chọn Video Files" />
                    <div class="mt-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900">
                        @if(isset($userFiles) && (is_array($userFiles) ? count($userFiles) > 0 : $userFiles->count() > 0))
                            <div class="p-2 bg-gray-100 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">
                                Đã chọn: <span id="selected-files-count">{{ count($user_file_ids ?? []) }}</span> file(s)
                            </div>
                            <!-- Scrollable file list with fixed height -->
                            <div class="max-h-48 overflow-y-auto">
                                @foreach($userFiles as $file)
                                <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 transition-colors">
                                    <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer">
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file->original_name }}</p>
                                        <p class="text-xs text-gray-500">{{ \App\Helpers\SettingsHelper::formatBytes($file->size) }} • {{ $file->created_at->format('d/m/Y') }}</p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        @else
                            <div class="p-6 text-center text-gray-500">
                                <p class="text-sm">Vui lòng chọn user trước để xem danh sách file.</p>
                            </div>
                        @endif
                    </div>
                    </div>
                    @error('user_file_ids') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Platform Selection -->
                <div x-data="{ platform: @entangle('platform').live }">
                    <div>
                        <x-input-label value="Nền tảng phát trực tiếp" />
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($this->getPlatforms() as $key => $platformName)
                                <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200" :class="platform === '{{ $key }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-700'">
                                    <input type="radio" wire:model.live="platform" value="{{ $key }}" class="form-radio h-4 w-4 text-blue-600">
                                    <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $platformName }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('platform') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- RTMP Settings -->
                    <div class="grid grid-cols-1 gap-6 mt-6">
                        <div x-show="platform === 'custom'">
                            <x-input-label for="rtmp_url" value="RTMP URL Tùy Chỉnh" />
                            <x-text-input wire:model.defer="rtmp_url" id="rtmp_url" type="text" class="mt-1 block w-full" placeholder="rtmp://custom-server.com/live" />
                            @error('rtmp_url') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <x-input-label for="stream_key" value="Khóa Luồng (Stream Key)" />
                            <x-text-input wire:model.defer="stream_key" id="stream_key" type="password" class="mt-1 block w-full" placeholder="Nhập stream key từ platform" />
                            @error('stream_key') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Stream Settings Section -->
                <div class="border-t dark:border-gray-700 pt-6">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 border border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6 flex items-center">
                            <span class="bg-blue-100 dark:bg-blue-900 p-2 rounded-lg mr-3">⚙️</span>
                            Cài Đặt Stream
                        </h3>

                        <!-- Playlist Order -->
                        <div class="mb-6">
                            <x-input-label value="Thứ tự phát" class="text-sm font-medium" />
                            <select wire:model.defer="playlist_order" class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-blue-500 dark:focus:ring-blue-400 rounded-lg shadow-sm">
                                <option value="sequential">📋 Tuần tự (1→2→3)</option>
                                <option value="random">🎲 Ngẫu nhiên</option>
                            </select>
                            @error('playlist_order') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Checkbox Options Grid -->
                        <div class="space-y-4">
                            <!-- Loop Option -->
                            <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 transition-colors">
                                <input type="checkbox" wire:model.defer="loop" id="loop_checkbox"
                                       class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="loop_checkbox" class="ml-4 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-2">🔄</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lặp lại 24/7</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Phát liên tục không dừng, tự động lặp lại playlist</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Keep Files Option -->
                            <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-green-300 dark:hover:border-green-500 transition-colors">
                                <input type="checkbox" wire:model.defer="keep_files_on_agent" id="keep_files_checkbox"
                                       class="mt-1 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                <label for="keep_files_checkbox" class="ml-4 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-2">💾</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Giữ file trên VPS agent</span>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                                                <p>✅ <strong>Bật:</strong> File được giữ lại trên VPS để stream nhanh hơn lần sau</p>
                                                <p>🗑️ <strong>Tắt:</strong> File tự động xóa khỏi VPS để tiết kiệm dung lượng</p>
                                                <p class="text-amber-600 dark:text-amber-400">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <strong>Lưu ý:</strong> File trên CDN vẫn được giữ, chỉ xóa trên VPS
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Schedule Option -->
                            <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-purple-300 dark:hover:border-purple-500 transition-colors">
                                <input type="checkbox" wire:model.live="enable_schedule" id="schedule_checkbox"
                                       class="mt-1 h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="schedule_checkbox" class="ml-4 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-2">⏰</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lịch trình tự động</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tự động bắt đầu stream vào thời gian định sẵn</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Schedule DateTime (Show when enabled) -->
                            @if($enable_schedule)
                            <div class="ml-8 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700 animate-fadeIn">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label value="⏰ Thời gian bắt đầu" class="text-sm font-medium text-purple-700 dark:text-purple-300" />
                                        <input type="datetime-local" wire:model.defer="scheduled_at"
                                               class="mt-2 block w-full border-purple-300 dark:border-purple-600 dark:bg-gray-800 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-400 focus:ring-purple-500 dark:focus:ring-purple-400 rounded-lg shadow-sm">
                                        @error('scheduled_at') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <x-input-label value="🏁 Thời gian kết thúc (tùy chọn)" class="text-sm font-medium text-purple-700 dark:text-purple-300" />
                                        <input type="datetime-local" wire:model.defer="scheduled_end"
                                               class="mt-2 block w-full border-purple-300 dark:border-purple-600 dark:bg-gray-800 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-400 focus:ring-purple-500 dark:focus:ring-purple-400 rounded-lg shadow-sm">
                                        @error('scheduled_end') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end p-6 border-t dark:border-gray-700 flex-shrink-0">
            <x-secondary-button wire:click="$set('showCreateModal', false)" type="button">Hủy</x-secondary-button>
            <x-primary-button type="submit" form="create-stream-form" class="ml-4">
                {{ $editingStream ? 'Lưu Thay Đổi' : 'Tạo Stream' }}
            </x-primary-button>
        </div>
    </div>
</x-modal-v2>

<!-- Edit Stream Modal - Refactored for proper scrolling -->
<x-modal-v2 wire:model.live="showEditModal" max-width="2xl">
    <div class="flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 flex-shrink-0">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Chỉnh Sửa Stream</h2>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="flex-grow overflow-y-auto px-6">
            <form id="edit-stream-form" wire:submit.prevent="update" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <x-input-label for="title" value="Tên Stream" />
                        <x-text-input wire:model.defer="title" id="title" type="text" class="mt-1 block w-full" placeholder="Nhập tên stream" />
                        @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-input-label for="description" value="Mô tả (tùy chọn)" />
                        <textarea wire:model.defer="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" placeholder="Mô tả ngắn về stream của bạn"></textarea>
                        @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- File Selection -->
                <div>
                    <x-input-label value="Chọn Video Files" />
                    <div class="mt-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                        @forelse($this->userFiles as $file)
                            @if($loop->first)
                            <div class="p-2 bg-gray-100 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">
                                Đã chọn: <span id="edit-selected-files-count">{{ count($user_file_ids ?? []) }}</span> file(s)
                            </div>
                            <!-- Scrollable file list with fixed height -->
                            <div class="max-h-48 overflow-y-auto">
                            @endif
                            <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 transition-colors">
                                <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer">
                                <div class="ml-3 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file->original_name }}</p>
                                    <p class="text-xs text-gray-500">{{ \App\Helpers\SettingsHelper::formatBytes($file->size) }} • {{ $file->created_at->format('d/m/Y') }}</p>
                                </div>
                            </label>
                            @if($loop->last)
                            </div>
                            @endif
                        @empty
                            <div class="p-6 text-center text-gray-500">
                                <p class="text-sm">Chưa có video nào trong thư viện.</p>
                            </div>
                        @endforelse
                    </div>
                    @error('user_file_ids') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <!-- Platform Selection -->
                <div x-data="{ platform: @entangle('platform').live }">
                    <div>
                        <x-input-label value="Nền tảng phát trực tiếp" />
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($this->getPlatforms() as $key => $platformName)
                                <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200" :class="platform === '{{ $key }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-700'">
                                    <input type="radio" wire:model.live="platform" value="{{ $key }}" class="form-radio h-4 w-4 text-blue-600">
                                    <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $platformName }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('platform') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- RTMP Settings -->
                    <div class="grid grid-cols-1 gap-6 mt-6">
                        <div x-show="platform === 'custom'">
                            <x-input-label for="rtmp_url" value="RTMP URL Tùy Chỉnh" />
                            <x-text-input wire:model.defer="rtmp_url" id="rtmp_url" type="text" class="mt-1 block w-full" placeholder="rtmp://custom-server.com/live" />
                            @error('rtmp_url') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <x-input-label for="stream_key" value="Khóa Luồng (Stream Key)" />
                            <x-text-input wire:model.defer="stream_key" id="stream_key" type="password" class="mt-1 block w-full" placeholder="Nhập stream key từ platform" />
                            @error('stream_key') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Advanced Settings -->
                <div class="border-t dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Cài đặt nâng cao</h3>

                    <!-- Playlist Order -->
                    <div class="mb-6">
                        <x-input-label value="Thứ tự phát" />
                        <select wire:model.defer="playlist_order" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option value="sequential">📋 Tuần tự</option>
                            <option value="random">🔀 Ngẫu nhiên</option>
                        </select>
                        @error('playlist_order') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Loop Option -->
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model.defer="loop" class="form-checkbox h-4 w-4 text-blue-600">
                            <span class="ml-2 text-sm text-gray-900 dark:text-gray-100">🔄 Lặp lại playlist (24/7 streaming)</span>
                        </label>
                        @error('loop') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex justify-end p-6 border-t dark:border-gray-700 flex-shrink-0">
            <x-secondary-button wire:click="$set('showEditModal', false)" type="button">Hủy</x-secondary-button>
            <x-primary-button type="submit" form="edit-stream-form" class="ml-4">Lưu Thay Đổi</x-primary-button>
        </div>
    </div>
</x-modal-v2>

@push('styles')
<style>
/* Custom scrollbar styling for file list */
.max-h-48::-webkit-scrollbar {
    width: 6px;
}

.max-h-48::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.max-h-48::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 3px;
}

.max-h-48::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.5);
}

/* Dark mode scrollbar */
.dark .max-h-48::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.dark .max-h-48::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
}

.dark .max-h-48::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>
@endpush
