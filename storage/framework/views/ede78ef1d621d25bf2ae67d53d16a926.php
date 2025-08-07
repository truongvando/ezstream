<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center gap-4">
            <div class="bg-red-100 dark:bg-red-900 p-3 rounded-lg">
                <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m-9-4V8a2 2 0 012-2h8a2 2 0 012 2v2M5 18h14a2 2 0 002-2v-8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">YouTube Services</h1>
                <p class="text-gray-600 dark:text-gray-400">Tăng hiệu suất cho kênh YouTube của bạn • <?php echo e(count($youtubeCategories)); ?> danh mục • 623 dịch vụ chuyên nghiệp</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Service Selection -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Service Selection Form -->
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chọn dịch vụ</h3>
                </div>
                
                <div class="space-y-4">
                    <!-- Main Category Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            📁 Chọn danh mục chính
                        </label>
                        <select wire:model.live="selectedCategory" 
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">-- Chọn danh mục --</option>
                            <?php
                                $categoryConfig = [
                                    'VIEWS' => 'Views - Tăng lượt xem',
                                    'SUBSCRIBERS' => 'Subscribers - Tăng subscriber',
                                    'LIVESTREAM' => 'Livestream - Live viewers',
                                    'LIKES' => 'Likes - Likes & Shares',
                                    'COMMENTS' => 'Comments - Comments & Replies'
                                ];
                            ?>
                            <?php $__currentLoopData = $categoryConfig; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryKey => $categoryLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <!--[if BLOCK]><![endif]--><?php if(isset($categoryStats[$categoryKey]) && $categoryStats[$categoryKey]['count'] > 0): ?>
                                    <option value="<?php echo e($categoryKey); ?>">
                                        <?php echo e($categoryLabel); ?> (<?php echo e($categoryStats[$categoryKey]['count']); ?> dịch vụ)
                                    </option>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </select>
                    </div>

                    <!-- Sub-Category Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            📋 Chọn loại dịch vụ
                        </label>
                        <select wire:model.live="selectedSubCategory" 
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                <?php if(!$selectedCategory): ?> disabled <?php endif; ?>>
                            <option value="">-- <?php echo e($selectedCategory ? 'Chọn loại dịch vụ' : 'Chọn danh mục trước'); ?> --</option>
                            <!--[if BLOCK]><![endif]--><?php if($selectedCategory && count($subCategories) > 0): ?>
                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $subCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $subStats = $subCategoryStats[$subCategory] ?? ['count' => 0, 'min_price' => 0];
                                    ?>
                                    <option value="<?php echo e($subCategory); ?>">
                                        <?php echo e($subCategory); ?> (<?php echo e($subStats['count']); ?> dịch vụ - từ $<?php echo e(number_format($subStats['min_price'], 4)); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </select>
                    </div>

                    <!-- Individual Service Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ⭐ Chọn dịch vụ cụ thể
                        </label>
                        <select wire:model.live="selectedService" 
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                <?php if(!$selectedSubCategory): ?> disabled <?php endif; ?>>
                            <option value="">-- <?php echo e($selectedSubCategory ? 'Chọn dịch vụ' : 'Chọn loại dịch vụ trước'); ?> --</option>
                            <!--[if BLOCK]><![endif]--><?php if($selectedSubCategory && count($categoryServices) > 0): ?>
                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $categoryServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        // JAP rate is per 1000 units, so show price per 1000 with markup
                                        $pricePerK = $service['rate'] * 1.2;
                                    ?>
                                    <option value="<?php echo e(json_encode($service)); ?>">
                                        <?php echo e($service['name']); ?> - $<?php echo e(number_format($pricePerK, 2)); ?>/1K 
                                        (<?php echo e(number_format($service['min'])); ?>-<?php echo e(number_format($service['max'])); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </select>
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['selectedService'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> 
                            <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> 
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>
            </div>

            <!-- Order Form - Always Visible -->
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Đặt hàng</h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            🔗 YouTube URL
                        </label>
                        <input wire:model.live="link" type="url"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               placeholder="https://youtube.com/watch?v=..."
                               <?php if(!$selectedServiceData): ?> disabled <?php endif; ?>>
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['link'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            🧮 Số lượng
                        </label>
                        <input wire:model.live="quantity" type="number"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500"
                               min="<?php echo e($selectedServiceData['min'] ?? 1); ?>"
                               max="<?php echo e($selectedServiceData['max'] ?? 999999); ?>"
                               placeholder="Nhập số lượng..."
                               <?php if(!$selectedServiceData): ?> disabled <?php endif; ?>>
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->

                        <!--[if BLOCK]><![endif]--><?php if($selectedServiceData): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Giới hạn: <?php echo e(number_format($selectedServiceData['min'])); ?> - <?php echo e(number_format($selectedServiceData['max'])); ?>

                            </p>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <!-- Advanced Options -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">⚙️ Tùy chọn nâng cao</h4>

                        <!-- Scheduled Order Toggle -->
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">🕐 Hẹn giờ đặt hàng</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Đặt hàng vào thời gian cụ thể</p>
                            </div>
                            <button wire:click="$toggle('enableScheduledOrder')"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 <?php echo e($enableScheduledOrder ? 'bg-red-600' : 'bg-gray-200 dark:bg-gray-700'); ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo e($enableScheduledOrder ? 'translate-x-6' : 'translate-x-1'); ?>"></span>
                            </button>
                        </div>

                        <!--[if BLOCK]><![endif]--><?php if($enableScheduledOrder): ?>
                            <div class="mb-3">
                                <input wire:model="scheduledDateTime" type="datetime-local"
                                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['scheduledDateTime'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                        <!-- Repeat Order Toggle -->
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">🔄 Đặt hàng lặp lại</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Tự động đặt lại sau khi hoàn thành</p>
                            </div>
                            <button wire:click="$toggle('enableRepeatOrder')"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 <?php echo e($enableRepeatOrder ? 'bg-red-600' : 'bg-gray-200 dark:bg-gray-700'); ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?php echo e($enableRepeatOrder ? 'translate-x-6' : 'translate-x-1'); ?>"></span>
                            </button>
                        </div>

                        <!--[if BLOCK]><![endif]--><?php if($enableRepeatOrder): ?>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Khoảng cách (giờ)</label>
                                    <input wire:model="repeatInterval" type="number" min="1" max="168"
                                           class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['repeatInterval'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Số lần lặp</label>
                                    <input wire:model="maxRepeats" type="number" min="1" max="100"
                                           class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['maxRepeats'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Service Info & Checkout -->
        <div class="space-y-6">
            <!-- Selected Service Details -->
            <!--[if BLOCK]><![endif]--><?php if($selectedServiceData): ?>
                <?php
                    // Extract refill info from service name (more accurate than API)
                    $serviceName = $selectedServiceData['name'];
                    $hasRefill = false;
                    $refillInfo = 'Không';
                    
                    if (stripos($serviceName, '[Refill: No]') !== false) {
                        $hasRefill = false;
                        $refillInfo = 'Không';
                    } elseif (preg_match('/\[Refill:\s*(\d+)\s*D(ays?)?\]/i', $serviceName, $matches)) {
                        $hasRefill = true;
                        $refillInfo = $matches[1] . ' ngày';
                    } elseif (stripos($serviceName, '[Refill: Lifetime]') !== false) {
                        $hasRefill = true;
                        $refillInfo = 'Trọn đời';
                    } elseif (stripos($serviceName, 'Refill') !== false) {
                        $hasRefill = true;
                        $refillInfo = 'Có';
                    }
                    
                    // JAP rate is per 1000 units, format correctly
                    $pricePerK = (float) $selectedServiceData['rate'] * 1.2;
                ?>
                
                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Dịch vụ đã chọn</h3>
                        <span class="ml-auto px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                            ID: <?php echo e($selectedServiceData['service']); ?>

                        </span>
                    </div>
                    
                    <!-- Service Name & Description -->
                    <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                            <?php echo e($serviceName); ?>

                        </div>
                        <?php
                            // Sử dụng Translation Service để tạo mô tả
                            $translationService = new \App\Services\TranslationService();
                            $description = $translationService->getServiceDescription($serviceName);
                        ?>
                        <div class="text-xs text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-600 pt-2 mt-2">
                            <strong>Mô tả:</strong> <?php echo e($description); ?>

                        </div>
                    </div>
                    
                    <!-- Service Stats -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Giá per 1K</div>
                            <div class="font-bold text-lg text-green-600 dark:text-green-400">
                                $<?php echo e(number_format($pricePerK, 2)); ?>

                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Số lượng</div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                <?php echo e(number_format($selectedServiceData['min'])); ?> - <?php echo e(number_format($selectedServiceData['max'])); ?>

                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Bảo hành</div>
                            <div class="font-medium <?php echo e($hasRefill ? 'text-green-600' : 'text-red-600'); ?>">
                                <?php echo e($refillInfo); ?>

                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Hủy đơn</div>
                            <div class="font-medium <?php echo e($selectedServiceData['cancel'] ? 'text-green-600' : 'text-red-600'); ?>">
                                <?php echo e($selectedServiceData['cancel'] ? 'Được' : 'Không'); ?>

                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa chọn dịch vụ</h3>
                        <p class="text-gray-600 dark:text-gray-400">Vui lòng chọn dịch vụ để xem thông tin chi tiết</p>
                    </div>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

            <!-- Price Display & Order Button -->
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Tổng thanh toán</h3>
                </div>

                <!--[if BLOCK]><![endif]--><?php if($selectedServiceData && $calculatedPrice > 0): ?>
                    <?php
                        // JAP rate is per 1000 units
                        $ratePer1000 = (float) $selectedServiceData['rate'] * 1.2;
                        $totalPrice = ($ratePer1000 * $quantity / 1000);
                        
                        // Sử dụng tỉ giá thực từ ExchangeRateService
                        $exchangeService = new \App\Services\ExchangeRateService();
                        $vndAmount = $exchangeService->convertUsdToVnd($totalPrice);
                    ?>
                    
                    <div class="text-center mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                            <?php echo e(number_format($quantity)); ?> units × $<?php echo e(number_format($ratePer1000, 2)); ?>/1K
                        </p>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                            $<?php echo e(number_format($totalPrice, 2)); ?>

                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            ≈ <?php echo e(number_format($vndAmount, 0, ',', '.')); ?> VND
                        </div>
                    </div>
                    
                    <!--[if BLOCK]><![endif]--><?php if($enableScheduledOrder || $enableRepeatOrder): ?>
                        <!-- Scheduled/Repeat Order Button -->
                        <button wire:click="placeScheduledOrder"
                                <?php if(!$selectedService || !$link || !$quantity): ?> disabled <?php endif; ?>
                                class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-6 rounded-lg transition-colors duration-200 mb-3">
                            <span wire:loading.remove wire:target="placeScheduledOrder" class="flex items-center justify-center gap-2">
                                <!--[if BLOCK]><![endif]--><?php if($enableScheduledOrder && $enableRepeatOrder): ?>
                                    🕐🔄 Hẹn giờ & Lặp lại - $<?php echo e(number_format($totalPrice * $maxRepeats, 2)); ?>

                                <?php elseif($enableScheduledOrder): ?>
                                    🕐 Hẹn giờ đặt hàng - $<?php echo e(number_format($totalPrice, 2)); ?>

                                <?php else: ?>
                                    🔄 Đặt hàng lặp lại - $<?php echo e(number_format($totalPrice * $maxRepeats, 2)); ?>

                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </span>
                            <span wire:loading wire:target="placeScheduledOrder" class="flex items-center justify-center gap-2">
                                ⏳ Đang tạo lịch...
                            </span>
                        </button>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                    <!-- Regular Order Button -->
                    <button wire:click="placeOrder"
                            <?php if(!$selectedService || !$link || !$quantity): ?> disabled <?php endif; ?>
                            class="w-full bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-6 rounded-lg transition-colors duration-200">
                        <span wire:loading.remove wire:target="placeOrder" class="flex items-center justify-center gap-2">
                            🛒 Đặt hàng ngay - $<?php echo e(number_format($totalPrice, 2)); ?>

                        </span>
                        <span wire:loading wire:target="placeOrder" class="flex items-center justify-center gap-2">
                            ⏳ Đang xử lý...
                        </span>
                    </button>
                    
                    <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-2">
                        🛡️ An toàn & Bảo mật • 🔄 Hỗ trợ bảo hành
                    </p>
                <?php else: ?>
                    <div class="text-center py-6">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">Chọn dịch vụ và nhập số lượng để xem giá</p>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </div>

            <!-- Recent Orders -->
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Đơn hàng gần đây</h3>
                </div>

                <!--[if BLOCK]><![endif]--><?php if($recentOrders && count($recentOrders) > 0): ?>
                    <div class="space-y-3">
                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $recentOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        #<?php echo e($order->id); ?>

                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php if($order->status === 'COMPLETED'): ?> bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        <?php elseif($order->status === 'PROCESSING'): ?> bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        <?php elseif($order->status === 'PENDING_FUNDS'): ?> bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        <?php elseif($order->status === 'PENDING_RETRY'): ?> bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                        <?php elseif($order->status === 'FAILED'): ?> bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        <?php elseif($order->status === 'REFUNDED'): ?> bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                        <?php elseif($order->status === 'CANCELLED'): ?> bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        <?php else: ?> bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        <?php endif; ?>">
                                        <?php echo e($order->status); ?>

                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                    <div><?php echo e(number_format($order->quantity)); ?> units</div>
                                    <div>$<?php echo e(number_format($order->total_amount, 2)); ?></div>
                                    <div><?php echo e($order->created_at->diffForHumans()); ?></div>
                                </div>

                                <!-- Action Buttons -->
                                <!--[if BLOCK]><![endif]--><?php if($order->canCancel()): ?>
                                    <div class="flex gap-2 mt-2">
                                        <button wire:click="cancelOrder(<?php echo e($order->id); ?>)"
                                                onclick="return confirm('Bạn có chắc muốn hủy đơn hàng này? Tiền sẽ được hoàn lại.')"
                                                class="px-2 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded transition-colors">
                                            🗑️ Hủy đơn
                                        </button>

                                        <!--[if BLOCK]><![endif]--><?php if($order->status === 'PENDING_FUNDS'): ?>
                                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-700 rounded">
                                                ⏳ Chờ xử lý
                                            </span>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                <?php elseif($order->status === 'PROCESSING' && $order->api_order_id): ?>
                                    <div class="flex gap-2 mt-2">
                                        <button wire:click="requestRefill(<?php echo e($order->id); ?>)"
                                                class="px-2 py-1 text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 rounded transition-colors">
                                            🔄 Bảo hành
                                        </button>
                                    </div>
                                <?php elseif($order->status === 'COMPLETED'): ?>
                                    <div class="flex gap-2 mt-2">
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded">
                                            ✅ Hoàn thành
                                        </span>
                                    </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">Chưa có đơn hàng nào</p>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/view-service-manager.blade.php ENDPATH**/ ?>