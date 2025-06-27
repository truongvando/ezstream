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

            <!-- Video Source -->
            <div>
                <x-input-label for="user_file_id" value="Chọn Video Nguồn" />
                <select wire:model.defer="user_file_id" id="user_file_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="">-- Chọn một video --</option>
                    @foreach($userFiles as $file)
                        <option value="{{ $file->id }}">
                            {{ \Illuminate\Support\Str::limit($file->original_name, 40) }} 
                            ({{ \Illuminate\Support\Number::fileSize($file->size, precision: 2) }})
                        </option>
                    @endforeach
                </select>
                @error('user_file_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                
                @if($user_id && empty($userFiles))
                    <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-1">
                        💡 User này chưa có video nào. Họ cần upload video từ Google Drive trước.
                    </p>
                @endif
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
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">⚙️ Tùy Chọn Stream</h3>
                <div class="space-y-4">
                    <!-- Stream Preset -->
                    <div>
                        <x-input-label value="Chất lượng Stream (Preset)" />
                        <div class="mt-2 space-y-2">
                            <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                <input type="radio" wire:model="stream_preset" value="direct" class="form-radio h-4 w-4 text-indigo-600">
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-200">🚀 Phát trực tiếp (Không mã hóa lại)</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">Tốt nhất cho VPS mạnh & video đã tối ưu. Không tốn CPU.</span>
                                </div>
                            </label>
                            <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                <input type="radio" wire:model="stream_preset" value="optimized" class="form-radio h-4 w-4 text-indigo-600">
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-200">⚡ Tối ưu hóa (CPU thấp)</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">Giảm chất lượng để stream mượt hơn trên VPS yếu.</span>
                                </div>
                            </label>
                            <label class="flex items-center p-3 rounded-lg border dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                <input type="radio" wire:model="stream_preset" value="high_quality" class="form-radio h-4 w-4 text-indigo-600">
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-200">🔥 Chất lượng cao</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">Chất lượng tốt nhất, yêu cầu VPS mạnh.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Loop -->
                    <div class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <input id="loop" wire:model.defer="loop" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <label for="loop" class="ml-3 flex items-center text-sm text-gray-900 dark:text-gray-300">
                            <span>🔄 Lặp lại video này (phát lại khi kết thúc)</span>
                        </label>
                    </div>

                    <!-- Scheduling -->
                    <div class="space-y-3">
                        <x-input-label for="scheduled_at" value="⏰ Lên lịch phát (tùy chọn)" />
                        <x-text-input wire:model.defer="scheduled_at" id="scheduled_at" type="datetime-local" class="mt-1 block w-full" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">Để trống nếu muốn phát ngay. Chọn ngày và giờ trong tương lai để lên lịch.</p>
                        
                        <x-input-label for="scheduled_end" value="⏰ Thời gian kết thúc (tùy chọn)" />
                        <x-text-input wire:model.defer="scheduled_end" id="scheduled_end" type="datetime-local" class="mt-1 block w-full" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">Để trống để stream không giới hạn thời gian.</p>
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