<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
    <!-- Tool Header -->
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-2">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mr-4"><?php echo e($tool->name); ?></h1>
                    <!--[if BLOCK]><![endif]--><?php if($tool->is_featured): ?>
                        <span class="bg-yellow-500 text-white text-sm font-bold px-3 py-1 rounded-full">
                            ⭐ Nổi bật
                        </span>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </div>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4"><?php echo e($tool->short_description); ?></p>
                
                <!-- Price -->
                <div class="flex items-center space-x-4">
                    <!--[if BLOCK]><![endif]--><?php if($tool->is_on_sale): ?>
                        <div class="flex items-center space-x-2">
                            <span class="line-through text-gray-400 text-xl">
                                <?php echo e(number_format($tool->price)); ?>đ
                            </span>
                            <span class="text-3xl font-bold text-red-600 dark:text-red-400">
                                <?php echo e(number_format($tool->final_price)); ?>đ
                            </span>
                            <span class="bg-red-500 text-white text-sm font-bold px-2 py-1 rounded">
                                -<?php echo e(round((($tool->price - $tool->sale_price) / $tool->price) * 100)); ?>%
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="text-3xl font-bold text-gray-900 dark:text-white">
                            <?php echo e(number_format($tool->final_price)); ?>đ
                        </span>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col space-y-3 ml-6">
                <!--[if BLOCK]><![endif]--><?php if(auth()->guard()->check()): ?>
                    <!--[if BLOCK]><![endif]--><?php if($userOrder && $userOrder->status === 'COMPLETED'): ?>
                        <!-- User owns the tool -->
                        <button wire:click="downloadTool" 
                                class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
                            📥 Download Tool
                        </button>
                        <!--[if BLOCK]><![endif]--><?php if($userLicense): ?>
                            <div class="text-center">
                                <div class="text-sm text-gray-600 dark:text-gray-400">License Key:</div>
                                <div class="font-mono text-sm bg-gray-100 dark:bg-gray-700 p-2 rounded">
                                    <?php echo e($userLicense->license_key); ?>

                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    <?php elseif($userOrder && $userOrder->status === 'PENDING'): ?>
                        <!-- Pending payment -->
                        <div class="text-center">
                            <div class="bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-4 py-2 rounded-lg">
                                ⏳ Đang chờ thanh toán
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Can purchase -->
                        <button wire:click="togglePurchaseModal" 
                                class="bg-purple-600 hover:bg-purple-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
                            🛒 Mua ngay
                        </button>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                <?php else: ?>
                    <a href="<?php echo e(route('login')); ?>" 
                       class="bg-purple-600 hover:bg-purple-700 text-white font-medium px-6 py-3 rounded-lg transition-colors text-center">
                        🔐 Đăng nhập để mua
                    </a>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <!--[if BLOCK]><![endif]--><?php if($tool->demo_url): ?>
                    <a href="<?php echo e($tool->demo_url); ?>" target="_blank"
                       class="bg-gray-600 hover:bg-gray-700 text-white font-medium px-6 py-3 rounded-lg transition-colors text-center">
                        👁️ Xem Demo
                    </a>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </div>
        </div>
    </div>

    <!-- Tool Content -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-6">
        <!-- Images Gallery -->
        <div>
            <div class="mb-4">
                <?php
                    $images = $tool->gallery ? array_merge([$tool->image], $tool->gallery) : [$tool->image];
                ?>
                <img src="<?php echo e($images[$activeImageIndex]); ?>" alt="<?php echo e($tool->name); ?>" 
                     class="w-full h-80 object-cover rounded-lg">
            </div>
            
            <!--[if BLOCK]><![endif]--><?php if(count($images) > 1): ?>
                <div class="flex space-x-2 overflow-x-auto">
                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button wire:click="setActiveImage(<?php echo e($index); ?>)"
                                class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition-colors
                                       <?php echo e($activeImageIndex === $index ? 'border-purple-500' : 'border-gray-300 dark:border-gray-600'); ?>">
                            <img src="<?php echo e($image); ?>" alt="Preview <?php echo e($index + 1); ?>" 
                                 class="w-full h-full object-cover">
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>

        <!-- Tool Information -->
        <div class="space-y-6">
            <!-- Features -->
            <!--[if BLOCK]><![endif]--><?php if($tool->features && count($tool->features) > 0): ?>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">✨ Tính năng</h3>
                    <ul class="space-y-2">
                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $tool->features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300"><?php echo e($feature); ?></span>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                    </ul>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

            <!-- System Requirements -->
            <!--[if BLOCK]><![endif]--><?php if($tool->system_requirements): ?>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">💻 Yêu cầu hệ thống</h3>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300"><?php echo e($tool->system_requirements); ?></p>
                    </div>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

            <!-- Description -->
            <div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">📝 Mô tả chi tiết</h3>
                <div class="prose dark:prose-invert max-w-none">
                    <p class="text-gray-700 dark:text-gray-300"><?php echo e($tool->description); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Tools -->
    <!--[if BLOCK]><![endif]--><?php if($relatedTools->count() > 0): ?>
        <div class="border-t border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">🔗 Tools liên quan</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $relatedTools; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $relatedTool): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('tools.show', $relatedTool->slug)); ?>" 
                       class="block border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <img src="<?php echo e($relatedTool->image); ?>" alt="<?php echo e($relatedTool->name); ?>" 
                             class="w-full h-32 object-cover rounded-lg mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-1"><?php echo e($relatedTool->name); ?></h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><?php echo e(Str::limit($relatedTool->short_description, 60)); ?></p>
                        <div class="text-lg font-bold text-purple-600 dark:text-purple-400">
                            <?php echo e(number_format($relatedTool->final_price)); ?>đ
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Purchase Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showPurchaseModal): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="togglePurchaseModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4" wire:click.stop>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">🛒 Xác nhận mua hàng</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Tool:</span>
                        <span class="font-medium text-gray-900 dark:text-white"><?php echo e($tool->name); ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Giá:</span>
                        <span class="font-bold text-purple-600 dark:text-purple-400"><?php echo e(number_format($tool->final_price)); ?>đ</span>
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900 dark:text-white">Tổng cộng:</span>
                            <span class="text-purple-600 dark:text-purple-400"><?php echo e(number_format($tool->final_price)); ?>đ</span>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 mt-6">
                    <button wire:click="togglePurchaseModal" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
                        Hủy
                    </button>
                    <button wire:click="purchase" 
                            class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="purchase">Xác nhận mua</span>
                        <span wire:loading wire:target="purchase">Đang xử lý...</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/tool-detail.blade.php ENDPATH**/ ?>