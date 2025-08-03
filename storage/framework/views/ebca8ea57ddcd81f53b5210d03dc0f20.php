<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">💳 Quản lý Payment</h2>
                <p class="text-gray-600 dark:text-gray-400">Toàn quyền kiểm soát hệ thống thanh toán</p>
            </div>
            <button wire:click="openManualModal"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
                ➕ Tạo giao dịch manual
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng giao dịch</div>
            <div class="text-2xl font-bold text-blue-600"><?php echo e(number_format($stats['total_transactions'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Thành công</div>
            <div class="text-2xl font-bold text-green-600"><?php echo e(number_format($stats['completed_transactions'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang chờ</div>
            <div class="text-2xl font-bold text-yellow-600"><?php echo e(number_format($stats['pending_transactions'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Thất bại</div>
            <div class="text-2xl font-bold text-red-600"><?php echo e(number_format($stats['failed_transactions'])); ?></div>
        </div>
    </div>

    <!-- Revenue Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng doanh thu</div>
            <div class="text-2xl font-bold text-green-600">$<?php echo e(number_format($stats['total_amount'], 2)); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Hôm nay</div>
            <div class="text-2xl font-bold text-blue-600">$<?php echo e(number_format($stats['today_amount'], 2)); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tháng này</div>
            <div class="text-2xl font-bold text-purple-600">$<?php echo e(number_format($stats['this_month_amount'], 2)); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tìm kiếm</label>
                <input wire:model.live="search" type="text" placeholder="User, email, mã GD..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trạng thái</label>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả</option>
                    <option value="COMPLETED">Thành công</option>
                    <option value="PENDING">Đang chờ</option>
                    <option value="FAILED">Thất bại</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gateway</label>
                <select wire:model.live="gatewayFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả</option>
                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($gateway); ?>"><?php echo e($gateway); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Từ ngày</label>
                <input wire:model.live="dateFrom" type="date"
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Đến ngày</label>
                <input wire:model.live="dateTo" type="date"
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div class="flex items-end">
                <button wire:click="$set('search', '')"
                        class="w-full bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg text-sm">
                    🔄 Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số tiền</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gateway</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thời gian</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">#<?php echo e($transaction->id); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($transaction->payment_code); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white"><?php echo e($transaction->user->name); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($transaction->user->email); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-bold text-green-600">$<?php echo e(number_format($transaction->amount, 2)); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($transaction->currency); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php if($transaction->payment_gateway === 'VIETQR_VCB'): ?> bg-blue-100 text-blue-800
                                    <?php elseif($transaction->payment_gateway === 'BALANCE_DEDUCTION'): ?> bg-purple-100 text-purple-800
                                    <?php elseif($transaction->payment_gateway === 'ADMIN_MANUAL'): ?> bg-orange-100 text-orange-800
                                    <?php elseif($transaction->payment_gateway === 'ADMIN_REFUND'): ?> bg-red-100 text-red-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e($transaction->payment_gateway); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php if($transaction->status === 'COMPLETED'): ?> bg-green-100 text-green-800
                                    <?php elseif($transaction->status === 'PENDING'): ?> bg-yellow-100 text-yellow-800
                                    <?php elseif($transaction->status === 'FAILED'): ?> bg-red-100 text-red-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e($transaction->status); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white"><?php echo e($transaction->created_at->format('d/m/Y H:i')); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($transaction->created_at->diffForHumans()); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <button wire:click="openTransactionModal(<?php echo e($transaction->id); ?>)"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                                        👁️ Xem
                                    </button>
                                    <!--[if BLOCK]><![endif]--><?php if($transaction->status === 'COMPLETED' && !isset($transaction->api_response['refunded'])): ?>
                                        <button wire:click="openRefundModal(<?php echo e($transaction->id); ?>)"
                                                class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">
                                            💸 Refund
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không có giao dịch nào
                            </td>
                        </tr>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <?php echo e($transactions->links()); ?>

        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showTransactionModal && $selectedTransaction): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">📋 Chi tiết giao dịch #<?php echo e($selectedTransaction->id); ?></h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
                        <div class="text-gray-900 dark:text-white"><?php echo e($selectedTransaction->user->name); ?></div>
                        <div class="text-sm text-gray-500"><?php echo e($selectedTransaction->user->email); ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền</label>
                        <div class="text-lg font-bold text-green-600">$<?php echo e(number_format($selectedTransaction->amount, 2)); ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gateway</label>
                        <div class="text-gray-900 dark:text-white"><?php echo e($selectedTransaction->payment_gateway); ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Trạng thái</label>
                        <span class="px-2 py-1 text-xs rounded-full
                            <?php if($selectedTransaction->status === 'COMPLETED'): ?> bg-green-100 text-green-800
                            <?php elseif($selectedTransaction->status === 'PENDING'): ?> bg-yellow-100 text-yellow-800
                            <?php elseif($selectedTransaction->status === 'FAILED'): ?> bg-red-100 text-red-800
                            <?php else: ?> bg-gray-100 text-gray-800
                            <?php endif; ?>">
                            <?php echo e($selectedTransaction->status); ?>

                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mã thanh toán</label>
                        <div class="text-gray-900 dark:text-white"><?php echo e($selectedTransaction->payment_code); ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thời gian</label>
                        <div class="text-gray-900 dark:text-white"><?php echo e($selectedTransaction->created_at->format('d/m/Y H:i:s')); ?></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mô tả</label>
                    <div class="text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700 p-3 rounded">
                        <?php echo e($selectedTransaction->description); ?>

                    </div>
                </div>

                <!--[if BLOCK]><![endif]--><?php if($selectedTransaction->api_response): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API Response</label>
                        <div class="text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 p-3 rounded overflow-x-auto">
                            <pre><?php echo e(json_encode($selectedTransaction->api_response, JSON_PRETTY_PRINT)); ?></pre>
                        </div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <div class="flex gap-3 mt-6">
                    <button wire:click="closeTransactionModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Đóng
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Refund Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showRefundModal): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">💸 Refund giao dịch</h3>

                <!--[if BLOCK]><![endif]--><?php if($selectedTransactionId): ?>
                    <?php $refundTransaction = \App\Models\Transaction::find($selectedTransactionId); ?>
                    <div class="mb-4 p-3 bg-gray-100 dark:bg-gray-700 rounded">
                        <div class="font-medium">Giao dịch #<?php echo e($refundTransaction->id); ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">User: <?php echo e($refundTransaction->user->name); ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Số tiền: $<?php echo e(number_format($refundTransaction->amount, 2)); ?></div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lý do refund</label>
                    <textarea wire:model="refundReason" rows="3" maxlength="255"
                              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                              placeholder="Nhập lý do refund..."></textarea>
                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['refundReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="processRefund"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        ✅ Xác nhận refund
                    </button>
                    <button wire:click="closeRefundModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Manual Transaction Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showManualModal): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">➕ Tạo giao dịch manual</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User ID</label>
                        <input wire:model="manualUserId" type="number" min="1"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="Nhập User ID...">
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['manualUserId'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Loại giao dịch</label>
                        <select wire:model="manualType" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="deposit">💰 Nạp tiền (Deposit)</option>
                            <option value="withdrawal">💸 Rút tiền (Withdrawal)</option>
                            <option value="adjustment">⚖️ Điều chỉnh (Adjustment)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Số tiền ($)</label>
                        <input wire:model="manualAmount" type="number" step="0.01" min="0.01" max="100000"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="0.00">
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['manualAmount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lý do</label>
                        <textarea wire:model="manualReason" rows="3" maxlength="255"
                                  class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                  placeholder="Nhập lý do giao dịch..."></textarea>
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['manualReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="createManualTransaction"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        ✅ Tạo giao dịch
                    </button>
                    <button wire:click="closeManualModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/payment-manager.blade.php ENDPATH**/ ?>