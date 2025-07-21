<div>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            VPS Real-time Monitoring
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Monitor VPS server performance and resource usage in real-time
        </p>
    </div>
    <div class="text-sm text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center">
            <svg class="w-4 h-4 mr-1 animate-pulse text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <circle cx="10" cy="10" r="3"/>
            </svg>
            Auto-refresh every 5s
        </span>
    </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
        $totalVps = count($vpsServers);
        $onlineVps = collect($vpsServers)->where('is_online', true)->count();
        $totalStreams = collect($vpsServers)->sum('current_streams');
        $totalCapacity = collect($vpsServers)->sum('max_streams');
    ?>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total VPS</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo e($totalVps); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Online VPS</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo e($onlineVps); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Streams</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo e($totalStreams); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Capacity</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo e($totalCapacity); ?></p>
            </div>
        </div>
    </div>
</div>


<div wire:poll.5s class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        VPS Server
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        CPU Usage
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        RAM Usage
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Disk Usage
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Streams
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Last Update
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $vpsServers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vps): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'transition-all duration-300',
                        'hover:bg-gray-50 dark:hover:bg-gray-700' => $vps['is_online'],
                        'opacity-60 bg-gray-100 dark:bg-gray-900' => !$vps['is_online'],
                    ]); ?>">
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                        'h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-sm',
                                        'bg-green-500' => $vps['is_online'],
                                        'bg-red-500' => !$vps['is_online'],
                                    ]); ?>">
                                        <?php echo e(strtoupper(substr($vps['name'], 0, 2))); ?>

                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo e($vps['name']); ?>

                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo e($vps['ip_address']); ?>

                                    </div>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $vps['is_online'],
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => !$vps['is_online'],
                            ]); ?>">
                                <svg class="w-1.5 h-1.5 mr-1.5" fill="currentColor" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3"/>
                                </svg>
                                <?php echo e($vps['status']); ?>

                            </span>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-900 dark:text-white"><?php echo e(number_format($vps['cpu_usage_percent'], 1)); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <?php ($cpuColor = $vps['cpu_usage_percent'] > 80 ? 'bg-red-500' : ($vps['cpu_usage_percent'] > 60 ? 'bg-yellow-400' : 'bg-blue-500')); ?>
                                        <div class="<?php echo e($cpuColor); ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo e($vps['cpu_usage_percent']); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-900 dark:text-white"><?php echo e(number_format($vps['ram_usage_percent'], 1)); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <?php ($ramColor = $vps['ram_usage_percent'] > 85 ? 'bg-red-500' : ($vps['ram_usage_percent'] > 70 ? 'bg-yellow-400' : 'bg-green-500')); ?>
                                        <div class="<?php echo e($ramColor); ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo e($vps['ram_usage_percent']); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-1">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-900 dark:text-white"><?php echo e(number_format($vps['disk_usage_percent'], 1)); ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <?php ($diskColor = $vps['disk_usage_percent'] > 90 ? 'bg-red-500' : ($vps['disk_usage_percent'] > 75 ? 'bg-yellow-400' : 'bg-purple-500')); ?>
                                        <div class="<?php echo e($diskColor); ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo e($vps['disk_usage_percent']); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo e($vps['current_streams']); ?> / <?php echo e($vps['max_streams']); ?>

                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400">
                                        <?php echo e(round(($vps['current_streams'] / max($vps['max_streams'], 1)) * 100)); ?>% used
                                    </div>
                                </div>
                            </div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div title="<?php echo e($vps['last_updated']); ?>">
                                <?php echo e($vps['last_updated']); ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No VPS Servers Found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new VPS server to start monitoring.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </tbody>
        </table>
    </div>
</div>
</div><?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/vps-monitoring.blade.php ENDPATH**/ ?>