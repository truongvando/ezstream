<!-- Stream Create/Edit Modal -->
<div x-data="{ 
    showSchedule: <?php if ((object) ('enable_schedule') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('enable_schedule'->value()); ?>')<?php echo e('enable_schedule'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('enable_schedule'); ?>')<?php endif; ?>,
    showAdvanced: false 
}" 
x-show="$wire.showCreateModal || $wire.showEditModal" 
x-cloak
class="fixed inset-0 z-50 overflow-y-auto"
style="display: none;">
    
    <!-- Backdrop -->
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             @click="$wire.closeModal()"></div>

        <!-- Modal -->
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        <?php echo e($showCreateModal ? 'Tạo Stream Mới' : 'Chỉnh Sửa Stream'); ?>

                    </h3>
                    <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="<?php echo e($showCreateModal ? 'store' : 'update'); ?>">
                <div class="bg-white dark:bg-gray-800 px-6 py-4 space-y-6">
                    
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tên Stream *
                            </label>
                            <input type="text" wire:model="title" 
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="Nhập tên stream...">
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['title'];
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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Mô Tả
                            </label>
                            <textarea wire:model="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                      placeholder="Mô tả stream..."></textarea>
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>

                    <!-- File Selection -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Chọn Video Files *
                            </label>
                            <a href="<?php echo e(route('files.index')); ?>"
                               target="_blank"
                               class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Upload Video
                            </a>
                        </div>
                        <div class="border border-gray-300 dark:border-gray-600 rounded-md p-4 max-h-48 overflow-y-auto">
                            <!--[if BLOCK]><![endif]--><?php if($userFiles && count($userFiles) > 0): ?>
                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $userFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="flex items-center space-x-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                        <input type="checkbox" wire:model="user_file_ids" value="<?php echo e($file->id); ?>"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <?php echo e($file->original_name); ?>

                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo e(number_format($file->size / 1024 / 1024, 1)); ?> MB
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                            <?php else: ?>
                                <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                                    Không có file nào. Vui lòng upload video trước.
                                </p>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['user_file_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <!-- Stream Settings Row -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Cài Đặt Stream
                        </h4>
                        <!-- Grid 2x2 cho các tùy chọn chính -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <!-- Loop -->
                            <div class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 h-20">
                                <input type="checkbox" wire:model="loop" id="loop_checkbox"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="loop_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-xl mr-3">🔄</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lặp lại 24/7</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát liên tục không dừng</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Keep Files -->
                            <div class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700 h-20">
                                <input type="checkbox" wire:model="keep_files_after_stop" id="keep_files_checkbox"
                                       class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                <label for="keep_files_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-xl mr-3">💾</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Giữ file sau khi dừng</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Không xóa để stream nhanh hơn lần sau</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Playlist Order - Sequential -->
                            <div class="flex items-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-700 h-20">
                                <input type="radio" wire:model="playlist_order" value="sequential" id="sequential_radio"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="sequential_radio" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-xl mr-3">📋</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Thứ tự tuần tự</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát theo thứ tự 1→2→3</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Playlist Order - Random -->
                            <div class="flex items-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700 h-20">
                                <input type="radio" wire:model="playlist_order" value="random" id="random_radio"
                                       class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300">
                                <label for="random_radio" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-xl mr-3">🎲</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Thứ tự ngẫu nhiên</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Phát ngẫu nhiên trong playlist</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Lịch trình tự động -->
                        <div class="mb-6">
                            <div class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700 mb-4">
                                <input type="checkbox" wire:model="enable_schedule" id="schedule_checkbox"
                                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                <label for="schedule_checkbox" class="ml-3 flex-1 cursor-pointer">
                                    <div class="flex items-center">
                                        <span class="text-xl mr-3">⏰</span>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Lịch trình tự động</span>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Tự động bắt đầu stream vào thời gian định sẵn</p>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Schedule Settings (Always visible, but disabled when not checked) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600"
                                 :class="{ 'opacity-50': !$wire.enable_schedule }">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ⏰ Thời Gian Bắt Đầu
                                    </label>
                                    <input type="datetime-local" wire:model="scheduled_at"
                                           :disabled="!$wire.enable_schedule"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-gray-100 disabled:bg-gray-100 dark:disabled:bg-gray-600 disabled:cursor-not-allowed"
                                           min="<?php echo e(now()->format('Y-m-d\TH:i')); ?>">
                                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['scheduled_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        🏁 Thời Gian Kết Thúc (Tùy chọn)
                                    </label>
                                    <input type="datetime-local" wire:model="scheduled_end"
                                           :disabled="!$wire.enable_schedule"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-gray-100 disabled:bg-gray-100 dark:disabled:bg-gray-600 disabled:cursor-not-allowed">
                                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['scheduled_end'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </div>
                        </div>

                        <!-- Ghi chú quản lý file -->
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700">
                            <div class="flex items-start">
                                <span class="text-xl mr-3 mt-0.5">⚠️</span>
                                <div class="text-sm text-amber-700 dark:text-amber-300">
                                    <p class="font-medium mb-2">📁 Quản lý file:</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                        <p>• <strong>Dừng stream:</strong> Giữ/xóa theo cài đặt</p>
                                        <p>• <strong>Xóa stream:</strong> Luôn xóa tất cả file</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Platform Settings -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            Cài Đặt Nền Tảng
                        </h4>
                        
                        <!-- Platform -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Nền Tảng
                            </label>
                            <select wire:model="platform"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                                <option value="youtube">YouTube Live</option>
                            </select>
                        </div>

                        <!-- Stream Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Stream Key *
                            </label>
                            <input type="text" wire:model="stream_key"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                                   placeholder="Nhập stream key từ YouTube...">
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['stream_key'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Hủy
                    </button>
                    <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <?php echo e($showCreateModal ? 'Tạo Stream' : 'Cập Nhật'); ?>

                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/shared/stream-modal.blade.php ENDPATH**/ ?>