<div wire:poll.5s class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Real-time Agent Reports Monitor</h2>
        <div class="flex items-center space-x-4">
            <?php if(count($events) > 0): ?>
                <div class="flex items-center text-sm text-green-600 dark:text-green-400">
                    <svg class="w-4 h-4 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo e(count($events)); ?> Live Reports
                </div>
            <?php else: ?>
                <div class="flex items-center text-sm text-yellow-600 dark:text-yellow-400">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    No Agent Data
                </div>
            <?php endif; ?>
            <div class="text-xs text-gray-400">
                Updates every 5s
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo e($event->created_at->format('H:i:s')); ?>

                            <div class="text-xs text-gray-400"><?php echo e($event->created_at->diffForHumans()); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php
                                $sourceClass = [
                                    'VPS_STATS' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'STREAM_UPDATE' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    'AGENT_HEARTBEAT' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                    'AGENT_REPORT' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                ][$event->type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($sourceClass); ?>">
                                <?php if($event->type === 'VPS_STATS'): ?>
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                                    </svg>
                                    Stats
                                <?php elseif($event->type === 'AGENT_HEARTBEAT'): ?>
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                    </svg>
                                    Heartbeat
                                <?php elseif($event->type === 'STREAM_UPDATE'): ?>
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                                    </svg>
                                    Stream
                                <?php else: ?>
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Agent
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo e(str_replace('_', ' ', $event->type)); ?>

                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white"><?php echo e($event->message); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            <?php if($event->context && $event->type === 'VPS_STATS'): ?>
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">CPU:</span>
                                        <span class="font-mono text-xs"><?php echo e(number_format($event->context['cpu_usage'] ?? 0, 1)); ?>%</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">RAM:</span>
                                        <span class="font-mono text-xs"><?php echo e(number_format($event->context['ram_usage'] ?? 0, 1)); ?>%</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">Streams:</span>
                                        <span class="font-mono text-xs"><?php echo e($event->context['active_streams'] ?? 0); ?></span>
                                    </div>
                                    <?php if(isset($event->context['disk_usage'])): ?>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">Disk:</span>
                                        <span class="font-mono text-xs"><?php echo e(number_format($event->context['disk_usage'], 1)); ?>%</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif($event->context && $event->type === 'AGENT_HEARTBEAT'): ?>
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">VPS:</span>
                                        <span class="font-mono text-xs">#<?php echo e($event->context['vps_id'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">Streams:</span>
                                        <span class="font-mono text-xs"><?php echo e(implode(', ', $event->context['active_streams'] ?? []) ?: 'None'); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs">TTL:</span>
                                        <span class="font-mono text-xs"><?php echo e($event->context['ttl_seconds'] ?? 0); ?>s</span>
                                    </div>
                                </div>
                            <?php elseif($event->context && $event->type === 'STREAM_UPDATE'): ?>
                                <span class="text-xs font-mono">Stream #<?php echo e($event->context['stream_id'] ?? 'N/A'); ?></span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center space-y-2">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>No agent reports yet. Waiting for VPS agents to connect...</div>
                                <div class="text-xs text-gray-400">Reports refresh every 5 seconds</div>
                                <div class="text-xs text-blue-500 mt-2">
                                    <span class="font-mono">php artisan agent:debug-reports</span> to troubleshoot
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views\livewire\admin\system-event-monitor.blade.php ENDPATH**/ ?>