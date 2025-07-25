<div wire:poll.5s class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Real-time Agent Reports Monitor</h2>
        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4 mr-1 animate-pulse text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <circle cx="10" cy="10" r="3"/>
            </svg>
            Live Agent Reports
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
                <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
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
                                    'AGENT_REPORT' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                ][$event->type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($sourceClass); ?>">
                                <!--[if BLOCK]><![endif]--><?php if($event->type === 'VPS_STATS'): ?>
                                    🖥️ VPS
                                <?php elseif($event->type === 'STREAM_UPDATE'): ?>
                                    🎬 Stream
                                <?php else: ?>
                                    🤖 Agent
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo e(str_replace('_', ' ', $event->type)); ?>

                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white"><?php echo e($event->message); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            <!--[if BLOCK]><![endif]--><?php if($event->context && $event->type === 'VPS_STATS'): ?>
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
                                </div>
                            <?php elseif($event->context && $event->type === 'STREAM_UPDATE'): ?>
                                <span class="text-xs font-mono">Stream #<?php echo e($event->context['stream_id'] ?? 'N/A'); ?></span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center space-y-2">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                <div>No agent reports yet. Waiting for VPS agents to connect...</div>
                                <div class="text-xs text-gray-400">Reports refresh every 5 seconds</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/admin/system-event-monitor.blade.php ENDPATH**/ ?>