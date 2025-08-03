<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">📦 Đơn hàng MMO</h1>
            <p class="text-gray-600 dark:text-gray-400">Theo dõi trạng thái và lịch sử đơn hàng dịch vụ MMO</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng đơn hàng</div>
            <div class="text-2xl font-bold text-blue-600"><?php echo e(number_format($stats['total_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang chờ</div>
            <div class="text-2xl font-bold text-yellow-600"><?php echo e(number_format($stats['pending_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang xử lý</div>
            <div class="text-2xl font-bold text-orange-600"><?php echo e(number_format($stats['processing_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Hoàn thành</div>
            <div class="text-2xl font-bold text-green-600"><?php echo e(number_format($stats['completed_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng chi tiêu</div>
            <div class="text-2xl font-bold text-purple-600">$<?php echo e(number_format($stats['total_spent'], 2)); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lọc theo trạng thái</label>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="PENDING">🟡 Đang chờ</option>
                    <option value="PROCESSING">🔵 Đang xử lý</option>
                    <option value="COMPLETED">🟢 Hoàn thành</option>
                    <option value="CANCELLED">🔴 Đã hủy</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="$set('statusFilter', '')"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                    🔄 Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    <div class="space-y-4 mb-6">
        <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="font-bold text-gray-900 dark:text-white">#<?php echo e($order->order_code); ?></h3>
                            <span class="px-3 py-1 text-sm rounded-full <?php echo e($order->status_badge); ?>">
                                <!--[if BLOCK]><![endif]--><?php if($order->status === 'PENDING'): ?> 🟡 Đang chờ
                                <?php elseif($order->status === 'PROCESSING'): ?> 🔵 Đang xử lý
                                <?php elseif($order->status === 'COMPLETED'): ?> 🟢 Hoàn thành
                                <?php elseif($order->status === 'CANCELLED'): ?> 🔴 Đã hủy
                                <?php elseif($order->status === 'REFUNDED'): ?> 🟠 Đã hoàn tiền
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Dịch vụ:</div>
                                <div class="font-medium text-gray-900 dark:text-white"><?php echo e($order->mmoService->name); ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Số tiền:</div>
                                <div class="font-bold text-green-600"><?php echo e($order->formatted_amount); ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Ngày đặt:</div>
                                <div class="text-gray-900 dark:text-white"><?php echo e($order->created_at->format('d/m/Y H:i')); ?></div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Thời gian giao hàng:</div>
                                <div class="text-gray-900 dark:text-white"><?php echo e($order->mmoService->delivery_time); ?></div>
                            </div>
                        </div>

                        <!--[if BLOCK]><![endif]--><?php if($order->customer_requirements && isset($order->customer_requirements['requirements'])): ?>
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Yêu cầu của bạn:</div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-sm text-gray-900 dark:text-white">
                                    <?php echo e($order->customer_requirements['requirements']); ?>

                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                        <!--[if BLOCK]><![endif]--><?php if($order->delivery_notes): ?>
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">📦 Ghi chú giao hàng:</div>
                                <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 text-sm text-green-800 dark:text-green-200">
                                    <?php echo e($order->delivery_notes); ?>

                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                        <!--[if BLOCK]><![endif]--><?php if($order->admin_notes): ?>
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">📝 Ghi chú admin:</div>
                                <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                                    <?php echo e($order->admin_notes); ?>

                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <div class="ml-4">
                        <button wire:click="openOrderModal(<?php echo e($order->id); ?>)"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                            👁️ Chi tiết
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📦</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa có đơn hàng nào</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Bạn chưa đặt đơn hàng dịch vụ MMO nào</p>
                <a href="<?php echo e(route('mmo-services.index')); ?>"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    🎮 Xem dịch vụ MMO
                </a>
            </div>
        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
    </div>

    <!-- Pagination -->
    <!--[if BLOCK]><![endif]--><?php if($orders->hasPages()): ?>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <?php echo e($orders->links()); ?>

        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Order Detail Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showOrderModal && $selectedOrder): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">📋 Chi tiết đơn hàng</h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mã đơn hàng</label>
                            <div class="text-gray-900 dark:text-white font-mono"><?php echo e($selectedOrder->order_code); ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Trạng thái</label>
                            <span class="px-3 py-1 text-sm rounded-full <?php echo e($selectedOrder->status_badge); ?>">
                                <!--[if BLOCK]><![endif]--><?php if($selectedOrder->status === 'PENDING'): ?> 🟡 Đang chờ
                                <?php elseif($selectedOrder->status === 'PROCESSING'): ?> 🔵 Đang xử lý
                                <?php elseif($selectedOrder->status === 'COMPLETED'): ?> 🟢 Hoàn thành
                                <?php elseif($selectedOrder->status === 'CANCELLED'): ?> 🔴 Đã hủy
                                <?php elseif($selectedOrder->status === 'REFUNDED'): ?> 🟠 Đã hoàn tiền
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dịch vụ</label>
                            <div class="text-gray-900 dark:text-white"><?php echo e($selectedOrder->mmoService->name); ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền</label>
                            <div class="text-lg font-bold text-green-600"><?php echo e($selectedOrder->formatted_amount); ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày đặt</label>
                            <div class="text-gray-900 dark:text-white"><?php echo e($selectedOrder->created_at->format('d/m/Y H:i:s')); ?></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thời gian giao hàng</label>
                            <div class="text-gray-900 dark:text-white"><?php echo e($selectedOrder->mmoService->delivery_time); ?></div>
                        </div>
                    </div>

                    <!--[if BLOCK]><![endif]--><?php if($selectedOrder->customer_requirements && isset($selectedOrder->customer_requirements['requirements'])): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Yêu cầu của bạn</label>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-sm text-gray-900 dark:text-white">
                                <?php echo e($selectedOrder->customer_requirements['requirements']); ?>

                            </div>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                    <!--[if BLOCK]><![endif]--><?php if($selectedOrder->delivery_notes): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📦 Ghi chú giao hàng</label>
                            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 text-sm text-green-800 dark:text-green-200">
                                <?php echo e($selectedOrder->delivery_notes); ?>

                            </div>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                    <!--[if BLOCK]><![endif]--><?php if($selectedOrder->admin_notes): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📝 Ghi chú admin</label>
                            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                                <?php echo e($selectedOrder->admin_notes); ?>

                            </div>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="closeOrderModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Đóng
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/user/mmo-orders.blade.php ENDPATH**/ ?>