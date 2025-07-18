<!-- Stream Create/Edit Modal - Đã sửa lỗi, chỉ còn YouTube và Custom RTMP, dùng đúng tên hàm updatedPlatform -->
<div x-data="{ showSchedule: @entangle('enable_schedule'), showAdvanced: false }"
     x-show="$wire.showCreateModal || $wire.showEditModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">
    <!-- Backdrop -->
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$wire.closeModal()"></div>
        <!-- Modal -->
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $showCreateModal ? 'Tạo Stream Mới' : 'Chỉnh Sửa Stream' }}
                    </h3>
                    <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Form -->
            <form wire:submit.prevent="{{ $showCreateModal ? 'store' : 'update' }}">
                <div class="bg-white dark:bg-gray-800 px-6 py-6 space-y-6">
                    <!-- Grid 2 cột: Thông tin cơ bản & File -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Thông tin cơ bản -->
                        <div class="space-y-4">
                            <!-- Tiêu đề -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tên Stream *</label>
                                <input type="text" wire:model="title" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100" placeholder="Nhập tên stream...">
                                @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <!-- Mô tả -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mô tả</label>
                                <textarea wire:model="description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100" placeholder="Mô tả stream..."></textarea>
                                @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <!-- Chọn file video -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Chọn Video Files *</label>
                            <div class="border border-gray-300 dark:border-gray-600 rounded-md p-3 max-h-40 overflow-y-auto bg-gray-50 dark:bg-gray-900">
                                @if($userFiles && count($userFiles) > 0)
                                    @foreach($userFiles as $file)
                                        <label class="flex items-center space-x-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                            <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <div class="flex-1">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $file->original_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($file->size / 1024 / 1024, 1) }} MB</div>
                                            </div>
                                        </label>
                                    @endforeach
                                @else
                                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">Không có file nào. Vui lòng upload video trước.</p>
                                @endif
                            </div>
                            @error('user_file_ids') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <!-- Cài đặt stream & nền tảng -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Cài đặt phát -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cài Đặt Stream</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="loop" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm">🔄 Lặp lại 24/7</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="keep_files_on_agent" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm">💾 Giữ file trên VPS</span>
                                </label>
                            </div>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" wire:model="playlist_order" value="sequential" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="ml-2 text-sm">📋 Tuần tự</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model="playlist_order" value="random" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300">
                                    <span class="ml-2 text-sm">🎲 Ngẫu nhiên</span>
                                </label>
                            </div>
                            <div class="flex items-center mt-2">
                                <input type="checkbox" wire:model="enable_schedule" id="schedule_checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="schedule_checkbox" class="ml-2 text-sm">⏰ Lịch trình tự động</label>
                            </div>
                            <div class="grid grid-cols-1 gap-2 mt-2" :class="{ 'opacity-50': !$wire.enable_schedule }">
                                <input type="datetime-local" wire:model="scheduled_at" :disabled="!$wire.enable_schedule" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-gray-100 disabled:bg-gray-100 dark:disabled:bg-gray-600 disabled:cursor-not-allowed" placeholder="Thời gian bắt đầu">
                                <input type="datetime-local" wire:model="scheduled_end" :disabled="!$wire.enable_schedule" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-gray-100 disabled:bg-gray-100 dark:disabled:bg-gray-600 disabled:cursor-not-allowed" placeholder="Thời gian kết thúc (tùy chọn)">
                            </div>
                        </div>
                        <!-- Cài đặt nền tảng (chỉ giữ phần này) -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cài Đặt Nền Tảng</label>
                            <div>
                                <select wire:model.live="platform" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                                    <option value="youtube">📺 YouTube</option>
                                    <option value="custom">🔧 Custom RTMP</option>
                                </select>
                                @error('platform') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            @if($platform === 'custom')
                            <div>
                                <input type="url" wire:model="rtmp_url" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100" placeholder="RTMP URL (rtmp://...)">
                                @error('rtmp_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            @else
                            <div>
                                <input type="text" wire:model="rtmp_url" readonly class="w-full px-3 py-2 bg-gray-100 dark:bg-gray-600 border border-gray-300 dark:border-gray-600 rounded-md text-gray-500 dark:text-gray-400" placeholder="Chọn nền tảng để tự động điền">
                            </div>
                            @endif
                            <div>
                                <input type="password" wire:model="stream_key" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100" placeholder="Stream Key *">
                                @error('stream_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    <!-- Nút hành động -->
                    <div class="flex justify-end pt-4 space-x-3 border-t border-gray-200 dark:border-gray-700 mt-4">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Hủy</button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">{{ $showCreateModal ? 'Tạo Stream' : 'Cập Nhật' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
