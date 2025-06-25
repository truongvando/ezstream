<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Quản Lý Stream') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <!-- Header & Create Button -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Stream của bạn</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Tạo và quản lý các luồng phát trực tiếp của bạn tại đây.</p>
                </div>
                <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tạo Stream Mới
                </button>
            </div>

            <!-- Streams Grid -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                @if($streams->count() > 0)
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 p-6">
                        @foreach ($streams as $stream)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow duration-300 bg-white dark:bg-gray-800 flex flex-col justify-between">
                                <div>
                                    <!-- Stream Status & Platform -->
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            @switch($stream->status)
                                                @case('ACTIVE')
                                                @case('STREAMING') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                @case('INACTIVE')
                                                @case('STOPPED') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                                @case('ERROR') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                @case('STARTING')
                                                @case('STOPPING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @endswitch
                                        ">{{ $stream->status }}</span>
                                        
                                        <div class="text-2xl" title="{{ $stream->platform }}">
                                            @if(str_contains($stream->rtmp_url, 'youtube')) 📺
                                            @elseif(str_contains($stream->rtmp_url, 'facebook')) 📘
                                            @elseif(str_contains($stream->rtmp_url, 'twitch')) 🎮
                                            @elseif(str_contains($stream->rtmp_url, 'instagram')) 📷
                                            @elseif(str_contains($stream->rtmp_url, 'tiktok')) 🎵
                                            @else ⚙️
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Stream Info -->
                                    <div class="mb-4">
                                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 truncate" title="{{ $stream->title }}">{{ $stream->title }}</h4>
                                        @if($stream->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2 h-10 overflow-hidden">{{ Str::limit($stream->description, 100) }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">VPS:</span> {{ $stream->vpsServer->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-auto">
                                    <div class="flex flex-wrap gap-2">
                                        @if(in_array($stream->status, ['ACTIVE', 'STREAMING', 'STARTING']))
                                            <button wire:click="stopStream({{ $stream->id }})" 
                                                    wire:loading.attr="disabled"
                                                    class="flex-1 text-center px-3 py-2 text-xs font-medium text-yellow-800 bg-yellow-100 rounded-md hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800 transition-colors duration-200">
                                                {{ $stream->status === 'STARTING' ? 'Đang Bắt Đầu...' : 'Dừng Stream' }}
                                            </button>
                                        @elseif(in_array($stream->status, ['INACTIVE', 'STOPPED', 'ERROR']))
                                            <button wire:click="startStream({{ $stream->id }})" 
                                                    wire:loading.attr="disabled"
                                                    class="flex-1 text-center px-3 py-2 text-xs font-medium text-green-800 bg-green-100 rounded-md hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800 transition-colors duration-200">
                                                {{ $stream->status === 'STOPPING' ? 'Đang Dừng...' : 'Bắt Đầu' }}
                                            </button>
                                        @endif
                                        
                                        <div class="flex-1 flex space-x-2">
                                            <button wire:click="edit({{ $stream->id }})" 
                                                    class="w-full px-3 py-2 text-xs font-medium text-blue-800 bg-blue-100 rounded-md hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition-colors duration-200">
                                                Sửa
                                            </button>
                                            
                                            <button wire:click="confirmDelete({{ $stream->id }})" 
                                                    class="w-full px-3 py-2 text-xs font-medium text-red-800 bg-red-100 rounded-md hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 transition-colors duration-200">
                                                Xóa
                                            </button>
                                        </div>
                                    </div>
                                    <button wire:click="$dispatch('showLogModal', { streamId: {{ $stream->id }} })" 
                                            class="mt-2 w-full px-3 py-2 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors duration-200">
                                        Xem Log
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Chưa có stream nào</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream đầu tiên của bạn.</p>
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

                @if($streams->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $streams->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>


    <!-- Add/Edit Stream Modal -->
    <x-modal-v2 wire:model.live="showCreateModal" max-width="2xl">
        <form wire:submit.prevent="{{ $editingStream ? 'update' : 'store' }}">
            <div class="p-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">{{ $editingStream ? 'Chỉnh Sửa Stream' : 'Tạo Stream Mới' }}</h2>
                
                <div class="space-y-6">
                    <!-- Title & Description -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-input-label for="title" value="Tiêu đề Stream" />
                            <x-text-input wire:model.defer="title" id="title" type="text" class="mt-1 block w-full" placeholder="VD: Livestream sự kiện X" />
                            @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Video Source -->
                    <div>
                        <x-input-label for="user_file_id" value="Chọn Video Nguồn" />
                        <select wire:model.defer="user_file_id" id="user_file_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option value="">-- Chọn một video --</option>
                            @foreach($userFiles as $file)
                                <option value="{{ $file->id }}">{{ $file->original_name }} ({{ \Illuminate\Support\Number::fileSize($file->size) }})</option>
                            @endforeach
                        </select>
                        @error('user_file_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Platform Selection -->
                    <div x-data="{ platform: @entangle('platform') }">
                        <x-input-label for="platform" value="Nền tảng Livestream" />
                        <select wire:model="platform" id="platform" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            @foreach($platforms as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>

                        <!-- Platform Specific Notes -->
                        <div x-show="platform === 'youtube'" class="mt-2 text-sm text-gray-500 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md">💡 <b>Mẹo YouTube:</b> Lấy RTMP URL và Khóa luồng từ trang <a href='https://www.youtube.com/live_dashboard' target='_blank' class='text-blue-500 hover:underline'>YouTube Live Control Room</a>.</div>
                        <div x-show="platform === 'facebook'" class="mt-2 text-sm text-gray-500 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md">💡 <b>Mẹo Facebook:</b> Sử dụng tùy chọn "Persistent Stream Key" (Khóa luồng không đổi) để không phải cập nhật lại khóa cho mỗi lần stream.</div>
                    </div>
                    
                    <!-- Stream Key & Custom RTMP URL -->
                    <div class="grid grid-cols-1 gap-6" x-data="{ platform: @entangle('platform') }">
                        <div x-show="platform === 'custom'">
                            <x-input-label for="rtmp_url" value="RTMP URL Tùy Chỉnh" />
                            <x-text-input wire:model.defer="rtmp_url" id="rtmp_url" type="text" class="mt-1 block w-full" placeholder="rtmp://..." />
                            @error('rtmp_url') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-input-label for="stream_key" value="Khóa Luồng (Stream Key)" />
                            <x-text-input wire:model.defer="stream_key" id="stream_key" type="password" class="mt-1 block w-full" />
                            @error('stream_key') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <hr class="dark:border-gray-700">

                    <!-- Streaming Options -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Tùy Chọn Stream</h3>
                        <div class="space-y-4">
                            <!-- Stream Preset -->
                            <div>
                                <x-input-label value="Chất lượng Stream (Preset)" />
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <input type="radio" wire:model="stream_preset" value="direct" class="form-radio h-4 w-4 text-indigo-600">
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-200">🚀 Trực tiếp</span>
                                            <span class="block text-sm text-gray-500 dark:text-gray-400">Không mã hóa lại.</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <input type="radio" wire:model="stream_preset" value="optimized" class="form-radio h-4 w-4 text-indigo-600">
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-200">⚡ Tối ưu</span>
                                            <span class="block text-sm text-gray-500 dark:text-gray-400">Mượt hơn trên VPS yếu.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Loop -->
                            <div class="flex items-center">
                                <input id="loop" wire:model.defer="loop" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                <label for="loop" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Lặp lại video này (phát lại khi kết thúc)
                                </label>
                            </div>

                            <!-- Scheduling -->
                            <div>
                                <x-input-label for="scheduled_at" value="Lên lịch phát (tùy chọn)" />
                                <x-text-input wire:model.defer="scheduled_at" id="scheduled_at" type="datetime-local" class="mt-1 block w-full" />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Để trống nếu muốn phát ngay. Chọn ngày và giờ trong tương lai để lên lịch.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end p-6 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 space-x-4">
                <x-secondary-button wire:click="closeModal" type="button">Hủy</x-secondary-button>
                <x-primary-button type="submit">
                    {{ $editingStream ? 'Lưu Thay Đổi' : 'Tạo Stream' }}
                </x-primary-button>
            </div>
        </form>
    </x-modal-v2>

    <!-- Delete Confirmation Modal -->
    <x-modal-v2 wire:model.live="showDeleteModal" max-width="md">
        <div class="p-6">
            <div class="text-center">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Xóa Stream</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Bạn có chắc chắn muốn xóa stream <strong class="font-medium text-gray-900 dark:text-white">{{ $deletingStream->title ?? '' }}</strong>? Hành động này không thể hoàn tác.
                </p>
            </div>
            <div class="mt-6 flex justify-center space-x-3">
                <x-secondary-button wire:click="closeModal">Hủy</x-secondary-button>
                <x-danger-button wire:click="delete">Xóa Stream</x-danger-button>
            </div>
        </div>
    </x-modal-v2>

    @livewire('log-viewer-modal')
</div>
