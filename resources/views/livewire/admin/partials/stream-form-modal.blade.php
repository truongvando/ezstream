<!-- Add/Edit Stream Modal -->
<x-modal-v2 wire:model.live="showCreateModal" max-width="2xl">
    <div class="p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">{{ $editingStream ? 'Chỉnh Sửa Stream' : 'Tạo Stream Mới' }}</h2>
        
        <form wire:submit.prevent="{{ $editingStream ? 'update' : 'store' }}" class="space-y-6">
            <!-- User Selection (Admin only) -->
            @if(auth()->user()->isAdmin())
            <div>
                <x-input-label for="user_id" value="User" />
                <select wire:model="user_id" wire:change="$refresh" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="">-- Chọn User --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                @error('user_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            @endif

            <!-- Title & Description -->
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <x-input-label for="title" value="Tiêu đề Stream" />
                    <x-text-input wire:model.defer="title" id="title" type="text" class="mt-1 block w-full" placeholder="VD: Livestream sự kiện X" />
                    @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <x-input-label for="description" value="Mô tả (tùy chọn)" />
                    <textarea wire:model.defer="description" id="description" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="3" placeholder="Mô tả chi tiết cho stream này..."></textarea>
                    @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Video Source (Multi-select) -->
            <div>
                <x-input-label for="user_file_ids" value="Chọn Video Nguồn (có thể chọn nhiều)" />
                
                @if($user_id && count($userFiles) > 0)
                    <div class="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900">
                        @foreach($userFiles as $file)
                            <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 border-b border-gray-200 dark:border-gray-700 last:border-b-0 cursor-pointer">
                                <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="ml-3 flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ \Illuminate\Support\Str::limit($file->original_name, 40) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Number::fileSize($file->size, precision: 2) }} • 
                                        @if($file->disk === 'google_drive')
                                            <span class="text-blue-600 dark:text-blue-400">📁 Google Drive</span>
                                        @else
                                            <span class="text-green-600 dark:text-green-400">💾 Local Storage</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    
                    @if(count($user_file_ids) > 0)
                        <div class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                            ✅ Đã chọn {{ count($user_file_ids) }} file(s)
                        </div>
                    @endif
                @elseif($user_id)
                    <div class="mt-2 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-md">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            💡 User này chưa có video nào. Họ cần upload video từ trang Quản lý File trước.
                        </p>
                    </div>
                @else
                    <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            👆 Vui lòng chọn User trước để xem danh sách video
                        </p>
                    </div>
                @endif
                
                @error('user_file_ids') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- Playlist Order -->
            <div>
                <x-input-label value="Thứ tự phát playlist" />
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" :class="$wire.playlist_order === 'sequential' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''">
                        <input type="radio" wire:model="playlist_order" value="sequential" class="form-radio h-4 w-4 text-blue-600">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">🔢 Tuần tự</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát theo thứ tự đã chọn</p>
                        </div>
                    </label>
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" :class="$wire.playlist_order === 'random' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''">
                        <input type="radio" wire:model="playlist_order" value="random" class="form-radio h-4 w-4 text-blue-600">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">🎲 Ngẫu nhiên</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát theo thứ tự random</p>
                        </div>
                    </label>
                </div>
                @error('playlist_order') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            
            <!-- AlpineJS Scope for Platform selection -->
            <div x-data="{ platform: @entangle('platform').live }">
                <!-- Platform Selection -->
                <div>
                    <x-input-label value="Nền tảng phát trực tiếp" />
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($this->getPlatforms() as $key => $platformName)
                            <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200" :class="platform === '{{ $key }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-700'">
                                <input type="radio" wire:model.live="platform" value="{{ $key }}" class="form-radio h-4 w-4 text-blue-600">
                                <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-200">{{ $platformName }}</span>
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

            <hr class="dark:border-gray-700">

            <!-- Streaming Options -->
            <div x-data="{ enable_schedule: @entangle('enable_schedule').live }">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">⚙️ Tùy Chọn Stream</h3>
                <div class="space-y-4">
                    <!-- Loop -->
                    <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <input id="loop" wire:model.defer="loop" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="loop" class="ml-3 flex items-center text-sm text-gray-900 dark:text-gray-300">
                            <span>🔄 Lặp lại playlist (phát lại vô hạn khi kết thúc, yêu cầu dừng thủ công)</span>
                        </label>
                    </div>

                    <!-- Scheduling Toggle -->
                     <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <input id="enable_schedule" wire:model.live="enable_schedule" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="enable_schedule" class="ml-3 flex items-center text-sm text-gray-900 dark:text-gray-300">
                            <span>⏰ Lên lịch phát</span>
                        </label>
                    </div>

                    <!-- Scheduling Fields -->
                    <div x-show="enable_schedule" class="space-y-3 border-l-4 border-indigo-500 pl-4 py-2" style="display: none;">
                        <div>
                            <x-input-label for="scheduled_at" value="Thời gian bắt đầu" />
                            <x-text-input wire:model.defer="scheduled_at" id="scheduled_at" type="datetime-local" class="mt-1 block w-full" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nếu để trống, sẽ phát ngay khi đến lượt.</p>
                        </div>
                        
                        <div>
                            <x-input-label for="scheduled_end" value="Thời gian kết thúc (tùy chọn)" />
                            <x-text-input wire:model.defer="scheduled_end" id="scheduled_end" type="datetime-local" class="mt-1 block w-full" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">Để trống để stream đến khi hết video (hoặc lặp vô hạn nếu chọn ở trên).</p>
                        </div>
                    </div>
                     <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                        Lưu ý: Nếu không Lên lịch, stream sẽ được bắt đầu ngay sau khi bạn nhấn nút "Tạo Stream".
                    </p>
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
            @if(auth()->user()->isAdmin())
            <div>
                <x-input-label for="user_id" value="User" />
                <select wire:model="user_id" wire:change="$refresh" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="">-- Chọn User --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                @error('user_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            @endif

            <!-- Title & Description -->
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <x-input-label for="title" value="Tiêu đề Stream" />
                    <x-text-input wire:model.defer="title" id="title" type="text" class="mt-1 block w-full" placeholder="VD: Livestream sự kiện X" />
                    @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <x-input-label for="description" value="Mô tả (tùy chọn)" />
                    <textarea wire:model.defer="description" id="description" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="3" placeholder="Mô tả chi tiết cho stream này..."></textarea>
                    @error('description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Video Source (Multi-select) -->
            <div>
                <x-input-label for="user_file_ids" value="Chọn Video Nguồn (có thể chọn nhiều)" />
                
                @if($user_id && count($userFiles) > 0)
                    <div class="mt-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-gray-900">
                        @foreach($userFiles as $file)
                            <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 border-b border-gray-200 dark:border-gray-700 last:border-b-0 cursor-pointer">
                                <input type="checkbox" wire:model="user_file_ids" value="{{ $file->id }}" class="form-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="ml-3 flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ \Illuminate\Support\Str::limit($file->original_name, 40) }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Number::fileSize($file->size, precision: 2) }} • 
                                        @if($file->disk === 'google_drive')
                                            <span class="text-blue-600 dark:text-blue-400">📁 Google Drive</span>
                                        @else
                                            <span class="text-green-600 dark:text-green-400">💾 Local Storage</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    
                    @if(count($user_file_ids) > 0)
                        <div class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                            ✅ Đã chọn {{ count($user_file_ids) }} file(s)
                        </div>
                    @endif
                @elseif($user_id)
                    <div class="mt-2 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-md">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            💡 User này chưa có video nào. Họ cần upload video từ trang Quản lý File trước.
                        </p>
                    </div>
                @else
                    <div class="mt-2 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            👆 Vui lòng chọn User trước để xem danh sách video
                        </p>
                    </div>
                @endif
                
                @error('user_file_ids') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- Playlist Order -->
            <div>
                <x-input-label value="Thứ tự phát playlist" />
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" :class="$wire.playlist_order === 'sequential' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''">
                        <input type="radio" wire:model="playlist_order" value="sequential" class="form-radio h-4 w-4 text-blue-600">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">🔢 Tuần tự</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát theo thứ tự đã chọn</p>
                        </div>
                    </label>
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" :class="$wire.playlist_order === 'random' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''">
                        <input type="radio" wire:model="playlist_order" value="random" class="form-radio h-4 w-4 text-blue-600">
                        <div class="ml-3">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-200">🎲 Ngẫu nhiên</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát theo thứ tự random</p>
                        </div>
                    </label>
                </div>
                @error('playlist_order') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>
            
            <!-- AlpineJS Scope for Platform selection -->
            <div x-data="{ platform: @entangle('platform').live }">
                <!-- Platform Selection -->
                <div>
                    <x-input-label value="Nền tảng phát trực tiếp" />
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($this->getPlatforms() as $key => $platformName)
                            <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200" :class="platform === '{{ $key }}' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-700'">
                                <input type="radio" wire:model.live="platform" value="{{ $key }}" class="form-radio h-4 w-4 text-blue-600">
                                <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-200">{{ $platformName }}</span>
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

            <!-- Action Buttons -->
            <div class="flex justify-end pt-6 border-t dark:border-gray-700 space-x-4">
                <x-secondary-button wire:click="$set('showEditModal', false)" type="button">Hủy</x-secondary-button>
                <x-primary-button type="submit">
                    ✅ Lưu Thay Đổi
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal-v2> 