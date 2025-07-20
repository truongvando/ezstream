<div x-show="$wire.showQuickStreamModal"
     x-cloak
     x-init="$watch('$wire.showQuickStreamModal', value => {
         if (value) {
             console.log('🎬 Quick Stream Modal opened');
             $dispatch('quickStreamModalOpened');
             // Ensure Alpine state is properly initialized
             $nextTick(() => {
                 if (typeof videoSource === 'undefined') {
                     videoSource = 'upload';
                 }
             });
         }
     })"
     class="quick-stream-modal fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
    
    <div class="fixed inset-0 transform transition-all" @click="$wire.showQuickStreamModal = false">
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-4xl sm:mx-auto max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 px-6 py-4 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        <svg class="inline w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Quick Stream - Tạo & Stream Ngay
                    </h3>
                    <button @click="$wire.showQuickStreamModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Form Body (Scrollable) -->
            <div class="flex-grow overflow-y-auto">
                <form wire:submit.prevent="createQuickStream" class="p-6">
                    <!-- Auto-Delete Warning -->
                    <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">⚠️ Lưu ý về Quick Stream</h3>
                                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li><strong>Video sẽ bị xóa vĩnh viễn</strong> sau khi stream kết thúc</li>
                                        <li>Phù hợp cho stream <strong>một lần duy nhất</strong> và tiết kiệm dung lượng</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column: Stream Info & Platform -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 border-b pb-2">1. Thông tin Stream</h4>
                            <!-- Title -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tiêu đề stream *</label>
                                <input type="text" wire:model="quickTitle" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nhập tiêu đề stream...">
                                <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['quickTitle'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mô tả</label>
                                <textarea wire:model="quickDescription" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Mô tả stream (tùy chọn)"></textarea>
                            </div>

                            <!-- Platform -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Platform *</label>
                                <div class="mt-1 relative">
                                    <select wire:model.live="quickPlatform" class="block w-full pl-10 pr-3 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="youtube">YouTube Live</option>
                                        <option value="custom">Custom RTMP</option>
                                    </select>
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <!--[if BLOCK]><![endif]--><?php if($quickPlatform === 'youtube'): ?>
                                            <!-- YouTube Icon -->
                                            <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        <?php else: ?>
                                            <!-- Custom RTMP Icon -->
                                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                                <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['quickPlatform'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                            </div>

                            <!-- RTMP URL (chỉ hiện khi custom) -->
                            <!--[if BLOCK]><![endif]--><?php if($quickPlatform === 'custom'): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">RTMP URL *</label>
                                <input type="url" wire:model="quickRtmpUrl" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="rtmp://your-server.com/live">
                                <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['quickRtmpUrl'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                            <!-- Stream Key -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stream Key *</label>
                                <input type="password" wire:model="quickStreamKey" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nhập stream key...">
                                <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['quickStreamKey'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        </div>

                        <!-- Right Column: Video Selection & Settings -->
                        <div class="space-y-4">
                            <!-- Video Selection -->
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 border-b pb-2">2. Video & Cài đặt</h4>

                                <!-- Upload hoặc Library tabs -->
                                <div x-data="{
                                    videoSource: 'upload',
                                    init() {
                                        console.log('🎯 Alpine tabs initialized, videoSource:', this.videoSource);
                                    },
                                    switchTab(tab) {
                                        console.log('🔄 Switching to tab:', tab);
                                        this.videoSource = tab;
                                    }
                                }" class="space-y-4">
                                    <div class="flex space-x-2">
                                        <button type="button"
                                                @click="switchTab('upload')"
                                                :class="videoSource === 'upload' ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                                                class="px-3 py-2 text-sm font-medium border rounded-md transition-colors flex items-center">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            Upload nhanh
                                        </button>
                                        <button type="button"
                                                @click="switchTab('library')"
                                                :class="videoSource === 'library' ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-gray-100 text-gray-700 border-gray-300'"
                                                class="px-3 py-2 text-sm font-medium border rounded-md transition-colors flex items-center">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                            Thư viện
                                        </button>
                                    </div>

                                    <!-- Upload Section -->
                                    <div x-show="videoSource === 'upload'">
                                        <?php echo $__env->make('livewire.shared.quick-upload-area', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                    </div>
                                    
                                    <!-- Library Selection -->
                                    <div x-show="videoSource === 'library'" x-transition>
                                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                                            <!--[if BLOCK]><![endif]--><?php if(isset($userFiles) && count($userFiles) > 0): ?>
                                                <div class="p-2 bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">
                                                    Đã chọn: <span x-text="$wire.quickSelectedFiles.length"></span> file(s)
                                                </div>
                                                <!-- Scrollable file list with fixed height -->
                                                <div class="max-h-48 overflow-y-auto">
                                                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $userFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <label class="quick-stream-file-label flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0 transition-colors"
                                                           @click="console.log('📋 File label clicked for file:', <?php echo e($file->id); ?>)">
                                                        <input type="checkbox"
                                                               wire:model.live="quickSelectedFiles"
                                                               value="<?php echo e($file->id); ?>"
                                                               class="quick-stream-checkbox form-checkbox h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer"
                                                               @change="console.log('✅ Checkbox changed, selected files:', $wire.quickSelectedFiles)"
                                                               @click.stop="console.log('📋 Direct checkbox click for file:', <?php echo e($file->id); ?>)">
                                                        <div class="ml-3 flex-1">
                                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($file->original_name); ?></p>
                                                            <p class="text-xs text-gray-500"><?php echo e(number_format($file->size / 1024 / 1024, 1)); ?>MB • <?php echo e($file->created_at->format('d/m/Y')); ?></p>
                                                        </div>
                                                    </label>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                </div>
                                            <?php else: ?>
                                                <div class="p-6 text-center text-gray-500">
                                                    <p class="text-sm">Chưa có video nào trong thư viện.</p>
                                                    <p class="text-xs mt-1">Hãy upload video trước hoặc sử dụng tab "Upload nhanh"</p>
                                                </div>
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stream Settings -->
                            <div class="space-y-3 pt-4 border-t">
                                <div class="flex items-center">
                                    <input type="checkbox" wire:model="quickLoop" id="quickLoop" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="quickLoop" class="ml-2 text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Stream lặp lại 24/7
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thứ tự phát</label>
                                    <select wire:model="quickPlaylistOrder" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="sequential">Tuần tự</option>
                                        <option value="random">Ngẫu nhiên</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Schedule -->
                            <div class="space-y-3 pt-4 border-t">
                                <div class="flex items-center">
                                    <input type="checkbox" wire:model.live="quickEnableSchedule" id="quickEnableSchedule" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="quickEnableSchedule" class="ml-2 text-sm font-medium flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Lên lịch stream
                                    </label>
                                </div>

                                <!--[if BLOCK]><![endif]--><?php if($quickEnableSchedule): ?>
                                <div class="ml-6 space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thời gian bắt đầu</label>
                                        <input type="datetime-local" wire:model.defer="quickScheduledAt" wire:ignore.self class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thời gian kết thúc</label>
                                        <input type="datetime-local" wire:model.defer="quickScheduledEnd" wire:ignore.self class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 dark:bg-gray-900 flex justify-end space-x-3 border-t pt-4 p-6 flex-shrink-0">
                <button type="button" @click="$wire.showQuickStreamModal = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Hủy</button>
                <button id="quickStreamSubmitButton"
                        wire:click="createQuickStream"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        type="button"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-all duration-200">
                    <span wire:loading.remove wire:target="createQuickStream" class="flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Tạo & Stream Ngay
                    </span>
                    <span wire:loading wire:target="createQuickStream" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Đang tạo...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Quick Stream Modal script loaded');

    // Debug Livewire events
    Livewire.on('quickStreamModalOpened', () => {
        console.log('🎯 Quick Stream Modal opened event received');

        // Force focus on modal to ensure it's interactive
        setTimeout(() => {
            const modal = document.querySelector('[x-show="$wire.showQuickStreamModal"]');
            if (modal) {
                modal.focus();
                console.log('🎯 Modal focused');
            }
        }, 100);
    });

    // Debug Quick Stream creation
    document.addEventListener('click', function(e) {
        if (e.target.id === 'quickStreamSubmitButton' || e.target.closest('#quickStreamSubmitButton')) {
            console.log('🚀 Quick Stream button clicked!', {
                quickTitle: window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('quickTitle'),
                quickPlatform: window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('quickPlatform'),
                quickStreamKey: window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('quickStreamKey'),
                quickSelectedFiles: window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('quickSelectedFiles'),
                video_source_id: window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).get('video_source_id')
            });
        }
    });

    // Debug checkbox interactions with more detail
    document.addEventListener('click', function(e) {
        if (e.target.type === 'checkbox' && e.target.hasAttribute('wire:model.live')) {
            console.log('📋 Checkbox clicked:', {
                value: e.target.value,
                checked: e.target.checked,
                wireModel: e.target.getAttribute('wire:model.live'),
                element: e.target,
                computedStyle: window.getComputedStyle(e.target),
                pointerEvents: window.getComputedStyle(e.target).pointerEvents
            });
        }

        // Debug any click on file labels
        if (e.target.closest('.quick-stream-file-label')) {
            console.log('📋 File label clicked:', e.target);
        }
    });

    // Debug Alpine.js state
    document.addEventListener('alpine:init', () => {
        console.log('🏔️ Alpine.js initialized');
    });

    // Additional debugging for modal interactions
    document.addEventListener('mousedown', function(e) {
        if (e.target.closest('[x-show="$wire.showQuickStreamModal"]')) {
            console.log('🖱️ Mouse down in modal:', e.target);
        }
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/shared/quick-stream-modal.blade.php ENDPATH**/ ?>