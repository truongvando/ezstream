<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">💰 Quản lý số dư</h2>
                <p class="text-gray-600 dark:text-gray-400">Điều chỉnh số dư user và xem thống kê bonus</p>
            </div>
        </div>
    </div>

    <!-- Bonus Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng bonus đã trả</div>
            <div class="text-2xl font-bold text-green-600">$<?php echo e(number_format($bonusStats['total_bonuses_paid'], 2)); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng nạp có bonus</div>
            <div class="text-2xl font-bold text-blue-600">$<?php echo e(number_format($bonusStats['total_deposits_with_bonus'], 2)); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">% bonus trung bình</div>
            <div class="text-2xl font-bold text-purple-600"><?php echo e(number_format($bonusStats['average_bonus_percentage'], 1)); ?>%</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Users có bonus</div>
            <div class="text-2xl font-bold text-orange-600"><?php echo e(number_format($bonusStats['users_with_bonuses'])); ?></div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Bonus tháng này</div>
            <div class="text-2xl font-bold text-red-600">$<?php echo e(number_format($bonusStats['bonuses_this_month'], 2)); ?></div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex gap-4">
            <div class="flex-1">
                <input wire:model.live="search" type="text" placeholder="Tìm user theo tên hoặc email..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số dư</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tổng nạp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tier bonus</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $bonusService = app(\App\Services\BonusService::class);
                            $currentTier = $bonusService->getBonusTier($user->total_deposits);
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white"><?php echo e($user->name); ?></div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($user->email); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-bold text-green-600">$<?php echo e(number_format($user->balance, 2)); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-medium text-blue-600">$<?php echo e(number_format($user->total_deposits, 2)); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php if($currentTier['name'] === 'none'): ?> bg-gray-100 text-gray-800
                                    <?php elseif($currentTier['name'] === '2%'): ?> bg-green-100 text-green-800
                                    <?php elseif($currentTier['name'] === '3%'): ?> bg-blue-100 text-blue-800
                                    <?php elseif($currentTier['name'] === '4%'): ?> bg-purple-100 text-purple-800
                                    <?php elseif($currentTier['name'] === '5%'): ?> bg-red-100 text-red-800
                                    <?php endif; ?>">
                                    <?php echo e($currentTier['name']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="openAdjustmentModal(<?php echo e($user->id); ?>)"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    💰 Điều chỉnh
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không có user nào
                            </td>
                        </tr>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <?php echo e($users->links()); ?>

        </div>
    </div>

    <!-- Adjustment Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showAdjustmentModal): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">💰 Điều chỉnh số dư</h3>

                <!--[if BLOCK]><![endif]--><?php if($selectedUserId): ?>
                    <?php $selectedUser = \App\Models\User::find($selectedUserId); ?>
                    <div class="mb-4 p-3 bg-gray-100 dark:bg-gray-700 rounded">
                        <div class="font-medium"><?php echo e($selectedUser->name); ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Số dư hiện tại: $<?php echo e(number_format($selectedUser->balance, 2)); ?></div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Loại điều chỉnh</label>
                        <select wire:model="adjustmentType" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="add">➕ Cộng tiền</option>
                            <option value="subtract">➖ Trừ tiền</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Số tiền ($)</label>
                        <input wire:model="adjustmentAmount" type="number" step="0.01" min="0.01" max="10000"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="0.00">
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['adjustmentAmount'];
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
                        <input wire:model="adjustmentReason" type="text" maxlength="255"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="Nhập lý do điều chỉnh...">
                        <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['adjustmentReason'];
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
                    <button wire:click="adjustBalance"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        ✅ Xác nhận
                    </button>
                    <button wire:click="closeAdjustmentModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/balance-manager.blade.php ENDPATH**/ ?>