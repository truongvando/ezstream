<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">💰 Nạp tiền vào tài khoản</h1>
        <p class="text-gray-600 dark:text-gray-400">Nạp tiền để sử dụng các dịch vụ trên EzStream</p>
    </div>

    <!-- Balance & Total Deposits -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Current Balance -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium opacity-90">Số dư hiện tại</h2>
                    <p class="text-3xl font-bold">$<?php echo e(number_format(auth()->user()->balance, 2)); ?></p>
                </div>
                <div class="text-6xl opacity-20">
                    💳
                </div>
            </div>
        </div>

        <!-- Total Deposits -->
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium opacity-90">Tổng đã nạp</h2>
                    <p class="text-3xl font-bold">$<?php echo e(number_format($totalDeposits, 2)); ?></p>
                </div>
                <div class="text-6xl opacity-20">
                    📈
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Deposit Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Nạp tiền mới</h3>
            
            <form wire:submit="createDeposit" class="space-y-4">
                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Số tiền (USD)
                    </label>
                    <input type="number" 
                           wire:model="amount"
                           id="amount" 
                           min="1" 
                           max="10000" 
                           step="0.01"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                           placeholder="Nhập số tiền muốn nạp">
                    <!--[if BLOCK]><![endif]--><?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><!--[if ENDBLOCK]><![endif]-->
                </div>

                <!-- Quick Amount Buttons -->
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" wire:click="$set('amount', 10)" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        $10
                    </button>
                    <button type="button" wire:click="$set('amount', 25)" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        $25
                    </button>
                    <button type="button" wire:click="$set('amount', 50)" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        $50
                    </button>
                    <button type="button" wire:click="$set('amount', 100)" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        $100
                    </button>
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Phương thức thanh toán
                    </label>
                    <div class="p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3">🏦</span>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Chuyển khoản ngân hàng</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Hệ thống sẽ tự động xác nhận thanh toán qua API ngân hàng</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Tiến hành nạp tiền
                </button>
            </form>
        </div>

        <!-- Recent Deposits -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Lịch sử nạp tiền gần đây</h3>
            
            <!--[if BLOCK]><![endif]--><?php if($recentDeposits->count() > 0): ?>
                <div class="space-y-3">
                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $recentDeposits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $deposit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo e($deposit->status === 'COMPLETED' ? 'bg-green-100 text-green-600' : ($deposit->status === 'PENDING' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600')); ?>">
                                    <!--[if BLOCK]><![endif]--><?php if($deposit->status === 'COMPLETED'): ?>
                                        ✓
                                    <?php elseif($deposit->status === 'PENDING'): ?>
                                        ⏳
                                    <?php else: ?>
                                        ✗
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        $<?php echo e(number_format($deposit->amount, 2)); ?>

                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e($deposit->created_at->format('d/m/Y H:i')); ?>

                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-medium <?php echo e($deposit->status === 'COMPLETED' ? 'text-green-600' : ($deposit->status === 'PENDING' ? 'text-yellow-600' : 'text-red-600')); ?>">
                                    <?php echo e($deposit->status); ?>

                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo e($deposit->payment_code); ?>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <div class="text-4xl mb-2">💳</div>
                    <p>Chưa có giao dịch nạp tiền nào</p>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>
    </div>

    <!-- Payment Modal -->
    <!--[if BLOCK]><![endif]--><?php if($showPaymentModal && $currentTransaction && $paymentInfo): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="closePaymentModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" wire:click.stop>
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">💳 Thanh toán nạp tiền</h2>
                        <button wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Transaction Info -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Mã giao dịch:</span>
                                <p class="font-medium text-gray-900 dark:text-white"><?php echo e($currentTransaction->payment_code); ?></p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Số tiền:</span>
                                <p class="font-bold text-blue-600 dark:text-blue-400 text-lg">$<?php echo e(number_format($currentTransaction->amount, 2)); ?></p>
                            </div>
                        </div>

                        <!-- Timeout Warning -->
                        <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900 rounded-lg border border-yellow-200 dark:border-yellow-700">
                            <div class="flex items-center">
                                <span class="text-yellow-600 dark:text-yellow-400 mr-2">⏰</span>
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                    Giao dịch sẽ tự động hủy sau <?php echo e(config('payment.transaction_timeout', 30)); ?> phút nếu không thanh toán
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- QR Code -->
                        <div class="text-center">
                            <div class="bg-white p-4 rounded-lg inline-block shadow-lg">
                                <img src="<?php echo e($paymentInfo['qr_code']); ?>" alt="QR Code" class="w-64 h-64 mx-auto object-contain">
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Quét mã QR để thanh toán</p>
                        </div>

                        <!-- Payment Details -->
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Ngân hàng:</label>
                                <p class="text-gray-900 dark:text-white"><?php echo e($paymentInfo['bank_name']); ?></p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Số tài khoản:</label>
                                <div class="flex items-center">
                                    <p class="text-gray-900 dark:text-white font-mono"><?php echo e($paymentInfo['account_number']); ?></p>
                                    <button onclick="copyToClipboard('<?php echo e($paymentInfo['account_number']); ?>')" class="ml-2 text-blue-600 hover:text-blue-800">
                                        📋
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Chủ tài khoản:</label>
                                <p class="text-gray-900 dark:text-white"><?php echo e($paymentInfo['account_name']); ?></p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền:</label>
                                <?php
                                    $exchangeService = new \App\Services\ExchangeRateService();
                                    $vndAmount = $exchangeService->convertUsdToVnd($paymentInfo['amount']);
                                    $rateInfo = $exchangeService->getRateInfo();
                                ?>
                                <div class="flex items-center">
                                    <p class="text-gray-900 dark:text-white font-bold text-lg"><?php echo e(number_format($vndAmount, 0, ',', '.')); ?> VND</p>
                                    <button onclick="copyToClipboard('<?php echo e(round($vndAmount)); ?>')" class="ml-2 text-blue-600 hover:text-blue-800">
                                        📋
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    ≈ $<?php echo e(number_format($paymentInfo['amount'], 2)); ?> USD
                                    (Tỉ giá: <?php echo e(number_format($rateInfo['rate'], 0, ',', '.')); ?>)
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nội dung chuyển khoản:</label>
                                <div class="flex items-center">
                                    <p class="text-gray-900 dark:text-white font-mono bg-gray-100 dark:bg-gray-600 px-2 py-1 rounded"><?php echo e($paymentInfo['content']); ?></p>
                                    <button onclick="copyToClipboard('<?php echo e($paymentInfo['content']); ?>')" class="ml-2 text-blue-600 hover:text-blue-800">
                                        📋
                                    </button>
                                </div>
                                <p class="text-xs text-red-500 mt-1">⚠️ Vui lòng nhập chính xác nội dung để được xử lý tự động</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-between mt-6">
                        <button wire:click="checkPaymentStatus" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                            🔄 Kiểm tra thanh toán
                        </button>
                        
                        <button wire:click="cancelTransaction" wire:confirm="Bạn có chắc muốn hủy giao dịch này?" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                            ❌ Hủy giao dịch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!-- Flash Messages -->
    <!--[if BLOCK]><![endif]--><?php if(session()->has('success')): ?>
        <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <?php if(session()->has('error')): ?>
        <div class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        toast.textContent = 'Đã sao chép!';
        document.body.appendChild(toast);
        
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 2000);
    });
}

// Auto check every 10 seconds if modal is open
setInterval(() => {
    if (<?php echo json_encode($showPaymentModal, 15, 512) ?>) {
        window.Livewire.find('<?php echo e($_instance->getId()); ?>').call('checkPaymentStatus');
    }
}, 10000);
</script>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/deposit-manager.blade.php ENDPATH**/ ?>