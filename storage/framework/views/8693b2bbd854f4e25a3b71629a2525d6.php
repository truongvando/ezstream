<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center mb-6">
        <div class="bg-green-100 dark:bg-green-900 p-3 rounded-lg mr-4">
            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">🔑 Quản lý License</h2>
            <p class="text-gray-600 dark:text-gray-400">Quản lý license keys và kích hoạt cho các tools đã mua</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-blue-100 dark:bg-blue-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100"><?php echo e($stats['total_licenses']); ?></div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">Tổng licenses</div>
                </div>
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-green-100 dark:bg-green-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100"><?php echo e($stats['active_licenses']); ?></div>
                    <div class="text-sm text-green-700 dark:text-green-300">Đang hoạt động</div>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-yellow-100 dark:bg-yellow-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100"><?php echo e($stats['activated_licenses']); ?></div>
                    <div class="text-sm text-yellow-700 dark:text-yellow-300">Đã kích hoạt</div>
                </div>
            </div>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-red-100 dark:bg-red-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-red-900 dark:text-red-100"><?php echo e($stats['expired_licenses']); ?></div>
                    <div class="text-sm text-red-700 dark:text-red-300">Đã hết hạn</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <input wire:model.live.debounce.300ms="search" type="text" 
                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                   placeholder="🔍 Tìm kiếm theo tên tool...">
        </div>
        
        <div>
            <select wire:model.live="statusFilter" 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="all">📋 Tất cả trạng thái</option>
                <option value="active">✅ Đang hoạt động</option>
                <option value="activated">⚡ Đã kích hoạt</option>
                <option value="not_activated">⏳ Chưa kích hoạt</option>
                <option value="expired">❌ Đã hết hạn</option>
            </select>
        </div>
    </div>

    <!-- Licenses List -->
    <div class="space-y-4 mb-6">
        <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $licenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $license): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 bg-white dark:bg-gray-700">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Tool Info -->
                        <div class="flex items-center mb-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mr-3">
                                <?php echo e($license->tool->name); ?>

                            </h3>
                            
                            <!-- Status Badge -->
                            <!--[if BLOCK]><![endif]--><?php if($license->is_expired): ?>
                                <span class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 text-xs font-medium px-2 py-1 rounded-full">
                                    ❌ Hết hạn
                                </span>
                            <?php elseif($license->is_activated): ?>
                                <span class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 text-xs font-medium px-2 py-1 rounded-full">
                                    ⚡ Đã kích hoạt
                                </span>
                            <?php elseif($license->is_active): ?>
                                <span class="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 text-xs font-medium px-2 py-1 rounded-full">
                                    ⏳ Chưa kích hoạt
                                </span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 text-xs font-medium px-2 py-1 rounded-full">
                                    ⏸️ Không hoạt động
                                </span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        <!-- License Key -->
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Key:</label>
                            <div class="flex items-center space-x-2">
                                <code class="bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white px-3 py-2 rounded font-mono text-sm flex-1">
                                    <?php echo e($license->license_key); ?>

                                </code>
                                <button wire:click="copyLicenseKey('<?php echo e($license->license_key); ?>')" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition-colors"
                                        title="Copy license key">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- License Details -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600 dark:text-gray-400">Ngày tạo:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    <?php echo e($license->created_at->format('d/m/Y H:i')); ?>

                                </span>
                            </div>
                            
                            <!--[if BLOCK]><![endif]--><?php if($license->expires_at): ?>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Hết hạn:</span>
                                    <span class="font-medium <?php echo e($license->is_expired ? 'text-red-600' : 'text-gray-900 dark:text-white'); ?>">
                                        <?php echo e($license->expires_at->format('d/m/Y H:i')); ?>

                                    </span>
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                            <!--[if BLOCK]><![endif]--><?php if($license->device_name): ?>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Thiết bị:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo e($license->device_name); ?></span>
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        <!-- Activation Info -->
                        <!--[if BLOCK]><![endif]--><?php if($license->activations->count() > 0): ?>
                            <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">📱 Lịch sử kích hoạt</h4>
                                <div class="space-y-1">
                                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $license->activations->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="text-sm text-blue-800 dark:text-blue-200">
                                            <span class="font-medium"><?php echo e($activation->device_name); ?></span>
                                            - <?php echo e($activation->activated_at->format('d/m/Y H:i')); ?>

                                            <span class="text-blue-600 dark:text-blue-400">(<?php echo e($activation->ip_address); ?>)</span>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($license->activations->count() > 3): ?>
                                        <div class="text-sm text-blue-600 dark:text-blue-400">
                                            +<?php echo e($license->activations->count() - 3); ?> lần kích hoạt khác
                                        </div>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col space-y-2 ml-4">
                        <!--[if BLOCK]><![endif]--><?php if($license->is_activated && $license->is_active): ?>
                            <button wire:click="showDeactivateModal(<?php echo e($license->id); ?>)" 
                                    class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                                🔓 Hủy kích hoạt
                            </button>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        
                        <a href="<?php echo e(route('tools.show', $license->tool->slug)); ?>" 
                           class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors text-center">
                           👁️ Xem tool
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-center py-12">
                <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">🔑</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa có license nào</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Mua tools để nhận license keys</p>
                <a href="<?php echo e(route('tools.index')); ?>" 
                   class="bg-purple-600 hover:bg-purple-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
                    🛠️ Xem cửa hàng tools
                </a>
            </div>
        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
    </div>

    <!-- Pagination -->
    <!--[if BLOCK]><![endif]--><?php if($licenses->hasPages()): ?>
        <div class="mt-6">
            <?php echo e($licenses->links()); ?>

        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Deactivate Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showDeactivateModal && $selectedLicense): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closeDeactivateModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4" wire:click.stop>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">🔓 Hủy kích hoạt License</h3>
                
                <div class="mb-4">
                    <p class="text-gray-600 dark:text-gray-400">
                        Bạn có chắc chắn muốn hủy kích hoạt license cho tool 
                        <strong class="text-gray-900 dark:text-white"><?php echo e($selectedLicense->tool->name); ?></strong>?
                    </p>
                    <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-2">
                        ⚠️ Sau khi hủy kích hoạt, bạn có thể kích hoạt lại trên thiết bị khác.
                    </p>
                </div>

                <div class="flex space-x-3">
                    <button wire:click="closeDeactivateModal" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
                        Hủy
                    </button>
                    <button wire:click="deactivateLicense" 
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="deactivateLicense">Xác nhận</span>
                        <span wire:loading wire:target="deactivateLicense">Đang xử lý...</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('copy-to-clipboard', (text) => {
            navigator.clipboard.writeText(text).then(() => {
                console.log('Copied to clipboard:', text);
            });
        });
    });
</script>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/license-manager.blade.php ENDPATH**/ ?>