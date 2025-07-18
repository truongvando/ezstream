<div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="p-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Lịch Sử Giao Dịch</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Theo dõi tất cả các giao dịch thanh toán và nâng cấp của bạn.</p>
            </div>
            
            <!--[if BLOCK]><![endif]--><?php if($transactions->count() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày Giao Dịch</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mã Giao Dịch</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mô Tả</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số Tiền</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng Thái</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        <?php echo e($transaction->created_at->format('d/m/Y H:i')); ?>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-800 dark:text-gray-100">
                                        <?php echo e($transaction->payment_code); ?>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        <?php echo e($transaction->description); ?>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e(number_format($transaction->amount, 0, ',', '.')); ?> VNĐ
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?php switch($transaction->status):
                                                case ('COMPLETED'): ?> bg-green-100 text-green-800 dark:bg-green-800/50 dark:text-green-200 <?php break; ?>
                                                <?php case ('PENDING'): ?> bg-yellow-100 text-yellow-800 dark:bg-yellow-800/50 dark:text-yellow-200 <?php break; ?>
                                                <?php case ('FAILED'): ?> bg-red-100 text-red-800 dark:bg-red-800/50 dark:text-red-200 <?php break; ?>
                                                <?php default: ?> bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                                            <?php endswitch; ?>
                                        ">
                                            <!--[if BLOCK]><![endif]--><?php switch($transaction->status):
                                                case ('COMPLETED'): ?> Hoàn thành <?php break; ?>
                                                <?php case ('PENDING'): ?> Đang chờ <?php break; ?>
                                                <?php case ('FAILED'): ?> Thất bại <?php break; ?>
                                                <?php default: ?> <?php echo e(ucfirst(strtolower($transaction->status))); ?>

                                            <?php endswitch; ?><!--[if ENDBLOCK]><![endif]-->
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <!--[if BLOCK]><![endif]--><?php if($transaction->status === 'PENDING'): ?>
                                            <button wire:click="cancelTransaction(<?php echo e($transaction->id); ?>)" 
                                                    wire:confirm="Bạn có chắc chắn muốn hủy giao dịch này không? Hành động này không thể hoàn tác."
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-semibold">
                                                Hủy
                                            </button>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </tbody>
                    </table>
                </div>
                
                <!--[if BLOCK]><![endif]--><?php if($transactions->hasPages()): ?>
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                        <?php echo e($transactions->links()); ?>

                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            <?php else: ?>
                <div class="text-center py-16">
                     <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Không có giao dịch nào</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Lịch sử các lần thanh toán của bạn sẽ được hiển thị tại đây.</p>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>
    </div>
</div> <?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/transaction-history.blade.php ENDPATH**/ ?>