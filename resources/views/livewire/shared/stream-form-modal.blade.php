@if($showCreateModal || $showEditModal)
{{-- Unified Stream Form Modal - Clean Layout --}}
<x-modal-v2 wire:model.live="{{ $showEditModal ? 'showEditModal' : 'showCreateModal' }}" max-width="3xl">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-h-[85vh] flex flex-col transition-all-smooth">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $editingStream ? 'Chỉnh Sửa Stream' : 'Tạo Stream Mới' }}
            </h2>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="flex-1 overflow-y-auto p-6 modal-scrollbar">
            <form id="{{ $editingStream ? 'edit-stream-form' : 'create-stream-form' }}" wire:submit.prevent="{{ $editingStream ? 'update' : 'store' }}" class="space-y-6">
                <!-- Basic Information Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                        Thông tin cơ bản
                    </h3>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label for="title" value="Tên Stream" />
                            <x-text-input wire:model.defer="title" id="title" type="text" class="mt-2 block w-full" placeholder="Nhập tên stream" />
                            @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <x-input-label for="description" value="Mô tả (tùy chọn)" />
                            <textarea wire:model.defer="description" id="description" rows="3" class="mt-2 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" placeholder="Mô tả ngắn về stream của bạn"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- File Selection Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                        Chọn Video Files
                    </h3>

                    <div class="border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900">
                        @if(isset($userFiles) && (is_array($userFiles) ? count($userFiles) > 0 : $userFiles->count() > 0))
                            <div class="px-4 py-3 bg-gray-100 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600 rounded-t-lg">
                                <span class="font-medium">Đã chọn:</span>
                                <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ count($user_file_ids ?? []) }}</span> file(s)
                                @if($editingStream)
                                    <span class="ml-2 text-xs text-gray-500">(Editing: {{ $editingStream->title }})</span>
                                @endif
                            </div>
                            <!-- Scrollable file list with fixed height -->
                            <div class="max-h-48 overflow-y-auto">
                                @foreach($userFiles as $file)
                                @php
                                    $isStreamLibrary = $file->disk === 'bunny_stream';
                                    $processingStatus = $isStreamLibrary ? ($file->stream_metadata['processing_status'] ?? 'processing') : 'ready';
                                    $canSelect = !$isStreamLibrary || in_array($processingStatus, ['finished', 'completed', 'ready']);
                                @endphp
                                <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-700
                                       {{ $canSelect ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}
                                       border-b border-gray-200 dark:border-gray-600 last:border-b-0 transition-colors
                                       {{ in_array($file->id, $user_file_ids ?? []) ? 'bg-indigo-50 dark:bg-indigo-900/20' : '' }}">
                                    <input type="checkbox"
                                           wire:model.live="user_file_ids"
                                           value="{{ $file->id }}"
                                           {{ $canSelect ? '' : 'disabled' }}
                                           {{ in_array($file->id, $user_file_ids ?? []) ? 'checked' : '' }}
                                           class="form-checkbox h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $file->original_name }}
                                                @if(in_array($file->id, $user_file_ids ?? []))
                                                    <span class="ml-2 text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-200 px-2 py-1 rounded-full">✓ Đã chọn</span>
                                                @endif
                                            </p>
                                            @if($isStreamLibrary && $processingStatus !== 'completed')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    <svg class="animate-spin w-3 h-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Đang xử lý
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            {{ \App\Helpers\SettingsHelper::formatBytes($file->size) }} • {{ $file->created_at->format('d/m/Y') }}
                                            @if($isStreamLibrary)
                                                • Stream Library
                                            @endif
                                        </p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 011 1v1a1 1 0 01-1 1h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7H3a1 1 0 01-1-1V5a1 1 0 011-1h4zM9 3v1h6V3H9z"/>
                                </svg>
                                <p class="text-sm">Vui lòng chọn user trước để xem danh sách file.</p>
                            </div>
                        @endif
                    </div>
                    @error('user_file_ids') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Platform Selection Section -->
                <div class="space-y-4" x-data="{ platform: @entangle('platform').live }">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                        Nền tảng phát trực tiếp
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($this->getPlatforms() as $key => $platformName)
                            <label class="flex items-center p-4 rounded-lg border cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200"
                                   :class="platform === '{{ $key }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 ring-2 ring-indigo-500 ring-opacity-20' : 'border-gray-300 dark:border-gray-700'">
                                <input type="radio" wire:model.live="platform" value="{{ $key }}" class="form-radio h-5 w-5 text-indigo-600 focus:ring-indigo-500">

                                @if($key === 'youtube')
                                    <!-- YouTube Icon -->
                                    <svg class="ml-3 h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                    </svg>
                                @else
                                    <!-- Custom RTMP Icon -->
                                    <svg class="ml-3 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                @endif

                                <span class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $platformName }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('platform') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror

                    <!-- RTMP Settings -->
                    <div class="space-y-4 mt-6">
                        <div x-show="platform === 'custom'" x-transition>
                            <x-input-label for="rtmp_url" value="RTMP URL Tùy Chỉnh" />
                            <x-text-input wire:model.defer="rtmp_url" id="rtmp_url" type="text" class="mt-2 block w-full" placeholder="rtmp://custom-server.com/live" />
                            @error('rtmp_url') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <x-input-label for="stream_key" value="Khóa Luồng (Stream Key)" />
                            <x-text-input wire:model.defer="stream_key" id="stream_key" type="text" class="mt-2 block w-full" placeholder="Nhập stream key từ platform" />
                            @error('stream_key') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Stream Settings Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        Cài Đặt Stream
                    </h3>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 space-y-6">
                        <!-- Playlist Order -->
                        <div>
                            <x-input-label value="Thứ tự phát" class="text-sm font-medium mb-2" />
                            <select wire:model.defer="playlist_order" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-indigo-500 dark:focus:ring-indigo-400 rounded-lg shadow-sm">
                                <option value="sequential">Tuần tự (1→2→3)</option>
                                <option value="random">Ngẫu nhiên</option>
                            </select>
                            @error('playlist_order') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Stream Options -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Loop Option -->
                            <div class="flex items-start p-4 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-indigo-300 dark:hover:border-indigo-500 transition-colors">
                                <input type="checkbox" wire:model.defer="loop" id="loop_checkbox"
                                       class="mt-1 h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="loop_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 mr-2 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lặp lại 24/7</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Phát liên tục không dừng, tự động lặp lại playlist</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Keep Files Option -->
                            <div class="flex items-start p-4 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-green-300 dark:hover:border-green-500 transition-colors">
                                <input type="checkbox" wire:model.defer="keep_files_on_agent" id="keep_files_checkbox"
                                       class="mt-1 h-5 w-5 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                <label for="keep_files_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 mr-2 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Giữ file trên VPS</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">File được giữ lại để stream nhanh hơn lần sau</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Schedule Section -->
                        <div class="space-y-4">
                            <div class="flex items-center p-4 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                <input type="checkbox" wire:model.live="enable_schedule" id="schedule_checkbox"
                                       class="h-5 w-5 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="schedule_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lịch trình tự động</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tự động bắt đầu stream vào thời gian định sẵn</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Schedule DateTime (Show when enabled) -->
                            @if($enable_schedule)
                            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label value="Thời gian bắt đầu" class="text-sm font-medium" />
                                        <input type="datetime-local" wire:model.defer="scheduled_at" wire:ignore.self
                                               class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-400 focus:ring-purple-500 dark:focus:ring-purple-400 rounded-lg shadow-sm">
                                        @error('scheduled_at') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <x-input-label value="Thời gian kết thúc (tùy chọn)" class="text-sm font-medium" />
                                        <input type="datetime-local" wire:model.defer="scheduled_end" wire:ignore.self
                                               class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-400 focus:ring-purple-500 dark:focus:ring-purple-400 rounded-lg shadow-sm">
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
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <x-secondary-button wire:click="{{ $showEditModal ? '$set(\'showEditModal\', false)' : '$set(\'showCreateModal\', false)' }}" type="button" class="px-6 py-2">
                Hủy
            </x-secondary-button>
            <x-primary-button type="submit" form="{{ $editingStream ? 'edit-stream-form' : 'create-stream-form' }}" class="px-6 py-2">
                {{ $editingStream ? 'Lưu Thay Đổi' : 'Tạo Stream' }}
            </x-primary-button>
        </div>
    </div>
</x-modal-v2>
@endif

