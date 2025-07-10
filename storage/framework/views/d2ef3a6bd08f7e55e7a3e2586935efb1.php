<div class="space-y-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stat Widgets -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tổng Users</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo e($stats['total_users']); ?></p>
                    </div>
                </div>
                <!-- Active Streams -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Streams Đang Chạy</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo e($stats['active_streams']); ?></p>
                    </div>
                </div>
                <!-- Active VPS -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">VPS Hoạt Động</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo e($stats['active_vps_servers']); ?></p>
                    </div>
                </div>
                <!-- Total Revenue -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Doanh Thu</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo e(number_format($stats['total_revenue'], 0, ',', '.')); ?> VNĐ</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Recent Streams -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Streams Gần Đây</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Stream</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">VPS</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Status</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $recentStreams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td class="py-3">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($stream->title); ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">by <?php echo e($stream->user->name); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300"><?php echo e($stream->vpsServer->name ?? 'N/A'); ?></td>
                                        <td class="py-3">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php switch($stream->status):
                                                    case ('ACTIVE'): ?>
                                                    <?php case ('STREAMING'): ?> bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 <?php break; ?>
                                                    <?php case ('INACTIVE'): ?>
                                                    <?php case ('STOPPED'): ?> bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 <?php break; ?>
                                                    <?php case ('ERROR'): ?> bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 <?php break; ?>
                                                    <?php case ('STARTING'): ?>
                                                    <?php case ('STOPPING'): ?> bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 <?php break; ?>
                                                    <?php default: ?> bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                <?php endswitch; ?>
                                            "><?php echo e($stream->status); ?></span>
                                        </td>
                                        <td class="py-3">
                                            <button class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Chi tiết</button>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">Không có stream nào gần đây.</td>
                                    </tr>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Recent Transactions -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Giao Dịch Gần Đây</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">User</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Amount</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Package</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300"><?php echo e($transaction->user->name); ?></td>
                                        <td class="py-3 text-sm font-medium text-gray-900 dark:text-white"><?php echo e(number_format($transaction->amount, 0, ',', '.')); ?> VNĐ</td>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300"><?php echo e($transaction->servicePackage->name ?? 'N/A'); ?></td>
                                        <td class="py-3">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo e($transaction->status === 'COMPLETED' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'); ?>">
                                                <?php echo e($transaction->status); ?>

                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">Không có giao dịch nào gần đây.</td>
                                    </tr>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- VPS Status -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Trạng Thái VPS</h3>
                        <div class="space-y-4">
                        <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $vpsStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vps): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-b-0">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($vps['name']); ?></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            <?php echo e($vps['status'] === 'online' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); ?>">
                                            <?php echo e($vps['status'] === 'online' ? '🟢 Online' : '🔴 Offline'); ?>

                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($vps['ip_address']); ?></span>
                                </div>
                                
                                <!--[if BLOCK]><![endif]--><?php if($vps['status'] === 'offline' && isset($vps['error'])): ?>
                                    <div class="mb-2 p-2 bg-red-50 dark:bg-red-900/20 rounded text-xs text-red-600 dark:text-red-400">
                                        Lỗi: <?php echo e($vps['error']); ?>

                                    </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">CPU</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400"><?php echo e(number_format($vps['cpu_usage_percent'], 2)); ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width:<?php echo e($vps['cpu_usage_percent']); ?>%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">RAM</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400"><?php echo e(number_format($vps['ram_usage_percent'], 2)); ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width:<?php echo e($vps['ram_usage_percent']); ?>%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">Disk</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400"><?php echo e(number_format($vps['disk_usage_percent'], 2)); ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-purple-500 h-2 rounded-full" style="width:<?php echo e($vps['disk_usage_percent']); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right text-xs text-gray-400 dark:text-gray-500 mt-2">
                                    Cập nhật: <?php echo e($vps['last_updated']); ?>

                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <p class="text-gray-500 dark:text-gray-400">Không có VPS nào đang hoạt động.</p>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>
                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Hành Động Nhanh</h3>
                        <div class="space-y-3">
                            <a href="<?php echo e(route('admin.streams')); ?>" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                🎬 Quản lý Streams
                            </a>
                            <a href="<?php echo e(route('admin.users')); ?>" class="block w-full text-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                👥 Quản lý Users
                            </a>
                            <a href="<?php echo e(route('admin.transactions')); ?>" class="block w-full text-center bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                💰 Quản lý Giao dịch
                            </a>
                            <a href="<?php echo e(route('admin.settings')); ?>" class="block w-full text-center bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                ⚙️ Cài đặt
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/dashboard.blade.php ENDPATH**/ ?>