<div x-data="{ activeTab: 'packages' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="p-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Quản lý Gói Dịch Vụ</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Nâng cấp, quản lý và xem thông tin gói cước của bạn.</p>
            </div>
        </div>

        <!-- Current Subscription -->
        <?php if($activeSubscription): ?>
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Gói Cước Hiện Tại</h2>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-blue-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Tên gói</p>
                            <p class="text-lg font-bold text-blue-900 dark:text-blue-100"><?php echo e($activeSubscription->servicePackage->name); ?></p>
                        </div>
                        <div class="bg-green-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm font-medium text-green-600 dark:text-green-300">Trạng thái</p>
                            <p class="text-lg font-bold text-green-900 dark:text-green-100 flex items-center">
                                <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                Đang hoạt động
                            </p>
                        </div>
                        <div class="bg-yellow-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm font-medium text-yellow-600 dark:text-yellow-300">Ngày bắt đầu</p>
                            <p class="text-lg font-bold text-yellow-900 dark:text-yellow-100"><?php echo e($activeSubscription->starts_at->format('d/m/Y')); ?></p>
                        </div>
                        <div class="bg-red-50 dark:bg-gray-700 p-4 rounded-lg">
                            <p class="text-sm font-medium text-red-600 dark:text-red-300">Ngày hết hạn</p>
                            <p class="text-lg font-bold text-red-900 dark:text-red-100"><?php echo e($activeSubscription->ends_at->format('d/m/Y')); ?></p>
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">Còn lại: <?php echo e(round(now()->diffInDays($activeSubscription->ends_at, false))); ?> ngày</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
             <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Bạn chưa có gói dịch vụ nào</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Vui lòng chọn một trong các gói bên dưới để bắt đầu.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Packages List -->
        <div class="mt-8">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Chọn hoặc Nâng cấp Gói Dịch Vụ</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col <?php if($activeSubscription && $activeSubscription->servicePackage->id === $package->id): ?> border-4 border-blue-500 <?php endif; ?>">
                        <div class="p-6 flex-grow">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo e($package->name); ?></h3>
                            <p class="mt-2 text-gray-600 dark:text-gray-300 h-12"><?php echo e($package->description); ?></p>
                            <div class="mt-4">
                                <span class="text-4xl font-extrabold text-gray-900 dark:text-white"><?php echo e(number_format($package->price, 0, ',', '.')); ?></span>
                                <span class="text-base font-medium text-gray-500 dark:text-gray-400">VNĐ/tháng</span>
                            </div>
                            <ul class="mt-6 space-y-4">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="ml-3 text-gray-700 dark:text-gray-300"><?php echo e($package->max_streams); ?> luồng đồng thời</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="ml-3 text-gray-700 dark:text-gray-300">Chất lượng tối đa <?php echo e($package->video_resolution); ?>p</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    <span class="ml-3 text-gray-700 dark:text-gray-300"><?php echo e($package->storage_limit_gb ? $package->storage_limit_gb . ' GB' : 'Không giới hạn'); ?> dung lượng lưu trữ</span>
                                </li>
                            </ul>
                        </div>
                        <div class="p-6 bg-gray-50 dark:bg-gray-700/50">
                           <?php if($activeSubscription): ?>
                                <?php if($activeSubscription->servicePackage->id === $package->id): ?>
                                    <button class="w-full bg-gradient-to-r from-emerald-500 to-emerald-600 text-white py-3 px-6 rounded-lg font-semibold cursor-not-allowed relative overflow-hidden">
                                        <span class="flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Gói hiện tại
                                        </span>
                                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-400 to-emerald-500 opacity-20"></div>
                                    </button>
                                <?php elseif($package->price > $activeSubscription->servicePackage->price): ?>
                                     <button wire:click="selectPackage(<?php echo e($package->id); ?>)" wire:loading.attr="disabled" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                        <span class="flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                            </svg>
                                            Nâng cấp
                                        </span>
                                    </button>
                                <?php else: ?>
                                    <button class="w-full bg-gradient-to-r from-gray-500 to-gray-600 text-white py-3 px-6 rounded-lg font-semibold cursor-not-allowed relative overflow-hidden" disabled title="Bạn không thể hạ cấp gói.">
                                        <span class="flex items-center justify-center opacity-90">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                                            </svg>
                                            Không khả dụng
                                        </span>
                                        <div class="absolute inset-0 bg-gray-400 opacity-20"></div>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button wire:click="selectPackage(<?php echo e($package->id); ?>)" wire:loading.attr="disabled" class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white py-3 px-6 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                                    <span class="flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Chọn gói
                                    </span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views\livewire\service-manager.blade.php ENDPATH**/ ?>