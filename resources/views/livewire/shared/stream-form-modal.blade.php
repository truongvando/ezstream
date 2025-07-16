<!-- Unified Stream Form Modal -->
<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}
</style>

<x-modal-v2 wire:model.live="showCreateModal" max-width="2xl">
    <div class="p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">
            {{ $editingStream ? 'Chỉnh Sửa Stream' : 'Tạo Stream Mới' }}
        </h2>
        
        <form wire:submit.prevent="{{ $editingStream ? 'update' : 'store' }}" class="space-y-6">
            <!-- User Selection (Admin only) -->
            @if(auth()->user()->isAdmin() && !$editingStream)
            <div>
                <x-input-label for="user_id" value="User" />
                <select wire:model="user_id" wire:change="$refresh" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="">-- Chọn User --</option>
                    @if(isset($users))
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    @endif
                </select>
                @error('user_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            @endif

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
                <div class="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-900">
                    @if(isset($userFiles) && (is_array($userFiles) ? count($userFiles) > 0 : $userFiles->count() > 0))
                        @foreach($userFiles as $file)
                            <label class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded cursor-pointer">
                                <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-4 w-4 text-blue-600">
                                <div class="ml-3 flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file->original_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($file->size / 1024 / 1024, 1) }} MB • 
                                        @if($file->disk === 'bunny_cdn')
                                            <span class="text-blue-600">☁️ Server</span>
                                        @elseif($file->disk === 'google_drive')
                                            <span class="text-green-600">📁 Google Drive</span>
                                        @else
                                            <span class="text-gray-600">💾 Local</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            @if(isset($user_id) && $user_id)
                                User này chưa có file nào. Vui lòng upload file trước.
                            @else
                                Vui lòng chọn user trước để xem danh sách file.
                            @endif
                        </p>
                    @endif
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
                            <input type="checkbox" wire:model.defer="keep_files_after_stop" id="keep_files_checkbox"
                                   class="mt-1 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                            <label for="keep_files_checkbox" class="ml-4 flex-1 cursor-pointer">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-2">💾</span>
                                    <div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Giữ file sau khi dừng stream</span>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                                            <p>✅ <strong>Bật:</strong> File được giữ lại để stream nhanh hơn lần sau</p>
                                            <p>🗑️ <strong>Tắt:</strong> File tự động xóa để tiết kiệm dung lượng VPS</p>
                                            <p class="text-amber-600 dark:text-amber-400">
                                                <span class="inline-flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <strong>Lưu ý:</strong> Khi xóa stream, tất cả file sẽ bị xóa vĩnh viễn
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

            <!-- Action Buttons -->
            <div class="flex justify-end pt-6 border-t dark:border-gray-700 space-x-4">
                <x-secondary-button wire:click="$set('showCreateModal', false)" type="button">Hủy</x-secondary-button>
                <x-primary-button type="submit">
                    {{ $editingStream ? '✅ Lưu Thay Đổi' : '🚀 Tạo Stream' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal-v2>

<!-- Edit Stream Modal -->
<x-modal-v2 wire:model.live="showEditModal" max-width="2xl">
    <div class="p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Chỉnh Sửa Stream</h2>

        <form wire:submit.prevent="update" class="space-y-6">
            <!-- User Selection (Admin only) -->
            @if(auth()->user()->isAdmin() && !$editingStream)
            <div>
                <x-input-label for="user_id" value="User" />
                <select wire:model="user_id" wire:change="$refresh" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="">-- Chọn User --</option>
                    @if(isset($users))
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    @endif
                </select>
                @error('user_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            @endif

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
                <div class="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-900">
                    @if(isset($userFiles) && (is_array($userFiles) ? count($userFiles) > 0 : $userFiles->count() > 0))
                        @foreach($userFiles as $file)
                            <label class="flex items-center p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded cursor-pointer">
                                <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-4 w-4 text-blue-600">
                                <div class="ml-3 flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file->original_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($file->size / 1024 / 1024, 1) }} MB •
                                        @if($file->disk === 'bunny_cdn')
                                            <span class="text-blue-600">☁️ Server</span>
                                        @elseif($file->disk === 'google_drive')
                                            <span class="text-green-600">📁 Google Drive</span>
                                        @else
                                            <span class="text-gray-600">💾 Local</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            @if(isset($user_id) && $user_id)
                                User này chưa có file nào. Vui lòng upload file trước.
                            @else
                                Vui lòng chọn user trước để xem danh sách file.
                            @endif
                        </p>
                    @endif
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

            <!-- Action Buttons -->
            <div class="flex justify-end pt-6 border-t dark:border-gray-700 space-x-4">
                <x-secondary-button wire:click="$set('showEditModal', false)" type="button">Hủy</x-secondary-button>
                <x-primary-button type="submit">✅ Lưu Thay Đổi</x-primary-button>
            </div>
        </form>
    </div>
</x-modal-v2>
