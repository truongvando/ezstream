<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">📦 Quản lý đơn hàng MMO</h2>
                <p class="text-gray-600 dark:text-gray-400">Xử lý và theo dõi đơn hàng dịch vụ MMO</p>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng đơn</div>
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
            <div class="text-sm text-gray-600 dark:text-gray-400">Đã hủy</div>
            <div class="text-2xl font-bold text-red-600"><?php echo e(number_format($stats['cancelled_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Doanh thu</div>
            <div class="text-2xl font-bold text-purple-600">$<?php echo e(number_format($stats['total_revenue'], 2)); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đơn hôm nay</div>
            <div class="text-2xl font-bold text-indigo-600"><?php echo e(number_format($stats['today_orders'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">DT hôm nay</div>
            <div class="text-2xl font-bold text-pink-600">$<?php echo e(number_format($stats['today_revenue'], 2)); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tìm kiếm</label>
                <input wire:model.live="search" type="text" placeholder="Mã đơn, user, dịch vụ..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trạng thái</label>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả</option>
                    <option value="PENDING">🟡 Đang chờ</option>
                    <option value="PROCESSING">🔵 Đang xử lý</option>
                    <option value="COMPLETED">🟢 Hoàn thành</option>
                    <option value="CANCELLED">🔴 Đã hủy</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dịch vụ</label>
                <select wire:model.live="serviceFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả dịch vụ</option>
                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($service->id); ?>"><?php echo e($service->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="$set('search', '')"
                        class="w-full bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg text-sm">
                    🔄 Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Đơn hàng</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Khách hàng</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Dịch vụ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số tiền</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày đặt</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">#<?php echo e($order->order_code); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">ID: <?php echo e($order->id); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white"><?php echo e($order->user->name); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($order->user->email); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white"><?php echo e($order->mmoService->name); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($order->mmoService->category); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-bold text-green-600"><?php echo e($order->formatted_amount); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo e($order->status_badge); ?>">
                                    <!--[if BLOCK]><![endif]--><?php if($order->status === 'PENDING'): ?> 🟡 Đang chờ
                                    <?php elseif($order->status === 'PROCESSING'): ?> 🔵 Đang xử lý
                                    <?php elseif($order->status === 'COMPLETED'): ?> 🟢 Hoàn thành
                                    <?php elseif($order->status === 'CANCELLED'): ?> 🔴 Đã hủy
                                    <?php elseif($order->status === 'REFUNDED'): ?> 🟠 Đã hoàn tiền
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white"><?php echo e($order->created_at->format('d/m/Y H:i')); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($order->created_at->diffForHumans()); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <button wire:click="openOrderModal(<?php echo e($order->id); ?>)"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                                        ✏️ Xử lý
                                    </button>
                                    <!--[if BLOCK]><![endif]--><?php if($order->status === 'PENDING'): ?>
                                        <button wire:click="quickUpdateStatus(<?php echo e($order->id); ?>, 'PROCESSING')"
                                                class="bg-orange-600 hover:bg-orange-700 text-white px-2 py-1 rounded text-xs">
                                            🔵 Xử lý
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    <!--[if BLOCK]><![endif]--><?php if(in_array($order->status, ['PENDING', 'PROCESSING'])): ?>
                                        <button wire:click="quickUpdateStatus(<?php echo e($order->id); ?>, 'COMPLETED')"
                                                class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">
                                            ✅ Hoàn thành
                                        </button>
                                        <button wire:click="quickUpdateStatus(<?php echo e($order->id); ?>, 'CANCELLED')"
                                                class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">
                                            ❌ Hủy
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không có đơn hàng nào
                            </td>
                        </tr>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <?php echo e($orders->links()); ?>

        </div>
    </div>

    <!-- Order Processing Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showOrderModal && $selectedOrder): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">📦 Xử lý đơn hàng #<?php echo e($selectedOrder->order_code); ?></h3>

                <!-- Order Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Left Column - Order Details -->
                    <div class="space-y-4">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-bold text-gray-900 dark:text-white mb-3">📋 Thông tin đơn hàng</h4>
                            <div class="space-y-2 text-sm">
                                <div><span class="font-medium">Mã đơn:</span> <?php echo e($selectedOrder->order_code); ?></div>
                                <div><span class="font-medium">Khách hàng:</span> <?php echo e($selectedOrder->user->name); ?></div>
                                <div><span class="font-medium">Email:</span> <?php echo e($selectedOrder->user->email); ?></div>
                                <div><span class="font-medium">Dịch vụ:</span> <?php echo e($selectedOrder->mmoService->name); ?></div>
                                <div><span class="font-medium">Số tiền:</span> <span class="text-green-600 font-bold"><?php echo e($selectedOrder->formatted_amount); ?></span></div>
                                <div><span class="font-medium">Ngày đặt:</span> <?php echo e($selectedOrder->created_at->format('d/m/Y H:i:s')); ?></div>
                                <div><span class="font-medium">Thời gian giao:</span> <?php echo e($selectedOrder->mmoService->delivery_time); ?></div>
                            </div>
                        </div>

                        <!--[if BLOCK]><![endif]--><?php if($selectedOrder->customer_requirements && isset($selectedOrder->customer_requirements['requirements'])): ?>
                            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-3">📝 Yêu cầu khách hàng</h4>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo e($selectedOrder->customer_requirements['requirements']); ?>

                                </div>
                                <!--[if BLOCK]><![endif]--><?php if(isset($selectedOrder->customer_requirements['quantity'])): ?>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                        Số lượng: <?php echo e($selectedOrder->customer_requirements['quantity']); ?>

                                    </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <!-- Right Column - Processing Form -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trạng thái mới</label>
                            <select wire:model="newStatus" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <option value="PENDING">🟡 Đang chờ</option>
                                <option value="PROCESSING">🔵 Đang xử lý</option>
                                <option value="COMPLETED">🟢 Hoàn thành</option>
                                <option value="CANCELLED">🔴 Hủy đơn</option>
                            </select>
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['newStatus'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ghi chú admin</label>
                            <textarea wire:model="adminNotes" rows="3" maxlength="1000"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Ghi chú nội bộ cho admin..."></textarea>
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['adminNotes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ghi chú giao hàng</label>
                            <textarea wire:model="deliveryNotes" rows="3" maxlength="1000"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Mô tả những gì đã giao cho khách hàng..."></textarea>
                            <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['deliveryNotes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        <!--[if BLOCK]><![endif]--><?php if($selectedOrder->mmoService->features): ?>
                            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                                <h4 class="font-bold text-gray-900 dark:text-white mb-2">✨ Tính năng dịch vụ</h4>
                                <ul class="text-sm text-gray-700 dark:text-gray-300 list-disc list-inside">
                                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $selectedOrder->mmoService->features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li><?php echo e($feature); ?></li>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                </ul>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>

                <!-- Current Notes Display -->
                <!--[if BLOCK]><![endif]--><?php if($selectedOrder->admin_notes || $selectedOrder->delivery_notes): ?>
                    <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mb-6">
                        <h4 class="font-bold text-gray-900 dark:text-white mb-3">📄 Ghi chú hiện tại</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!--[if BLOCK]><![endif]--><?php if($selectedOrder->admin_notes): ?>
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ghi chú admin:</div>
                                    <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                                        <?php echo e($selectedOrder->admin_notes); ?>

                                    </div>
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <!--[if BLOCK]><![endif]--><?php if($selectedOrder->delivery_notes): ?>
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ghi chú giao hàng:</div>
                                    <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 text-sm text-green-800 dark:text-green-200">
                                        <?php echo e($selectedOrder->delivery_notes); ?>

                                    </div>
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <div class="flex gap-3 mt-6">
                    <button wire:click="updateOrder"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg">
                        ✅ Cập nhật đơn hàng
                    </button>
                    <button wire:click="closeOrderModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/mmo-order-manager.blade.php ENDPATH**/ ?>