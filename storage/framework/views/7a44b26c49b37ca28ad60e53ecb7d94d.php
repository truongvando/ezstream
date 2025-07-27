<!DOCTYPE html>
<html>
<head>
    <title>Queue Monitor Test</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Auto refresh every 5 seconds
        setTimeout(() => location.reload(), 5000);
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">🚦 Stream Queue Monitor (Test)</h1>
        
        <!-- Queue Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">📋 Queue Status</h2>
            <?php
                $allocation = app(\App\Services\Stream\StreamAllocation::class);
                $queueStatus = $allocation->getQueueStatus();
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="bg-blue-50 p-4 rounded">
                    <div class="text-2xl font-bold text-blue-600"><?php echo e($queueStatus['total_queued']); ?></div>
                    <div class="text-blue-600">Streams in Queue</div>
                </div>
                <div class="bg-green-50 p-4 rounded">
                    <div class="text-2xl font-bold text-green-600"><?php echo e(now()->format('H:i:s')); ?></div>
                    <div class="text-green-600">Last Update</div>
                </div>
            </div>

            <?php if(!empty($queueStatus['streams'])): ?>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2">Stream ID</th>
                            <th class="border border-gray-300 px-4 py-2">Title</th>
                            <th class="border border-gray-300 px-4 py-2">User</th>
                            <th class="border border-gray-300 px-4 py-2">Priority</th>
                            <th class="border border-gray-300 px-4 py-2">Waiting Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $queueStatus['streams']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-2">#<?php echo e($stream['id']); ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?php echo e(Str::limit($stream['title'], 30)); ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?php echo e($stream['user']); ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?php echo e(number_format($stream['priority'], 0)); ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?php echo e(gmdate('H:i:s', $stream['waiting_time'])); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500">📭 Queue is empty</p>
            <?php endif; ?>
        </div>

        <!-- VPS Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🖥️ VPS Status</h2>
            <?php
                $vpsServers = \App\Models\VpsServer::where('status', 'ACTIVE')->get();
                $vpsStats = [];
                foreach($vpsServers as $vps) {
                    $statsJson = \Illuminate\Support\Facades\Redis::hget('vps_live_stats', $vps->id);
                    if($statsJson) {
                        $data = json_decode($statsJson, true);
                        $vpsStats[] = [
                            'id' => $vps->id,
                            'name' => $vps->name,
                            'cpu' => $data['cpu_usage'] ?? 0,
                            'ram' => $data['ram_usage'] ?? 0,
                            'streams' => $data['active_streams'] ?? 0,
                            'updated' => isset($data['received_at']) ? date('H:i:s', $data['received_at']) : 'N/A'
                        ];
                    }
                }
            ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php $__currentLoopData = $vpsStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vps): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border rounded-lg p-4 
                        <?php if($vps['cpu'] > 90): ?> border-red-300 bg-red-50
                        <?php elseif($vps['cpu'] > 70): ?> border-yellow-300 bg-yellow-50
                        <?php else: ?> border-green-300 bg-green-50 <?php endif; ?>">
                        
                        <h3 class="font-semibold"><?php echo e($vps['name']); ?> (ID: <?php echo e($vps['id']); ?>)</h3>
                        <div class="mt-2 space-y-1 text-sm">
                            <div>CPU: <strong><?php echo e(number_format($vps['cpu'], 1)); ?>%</strong></div>
                            <div>RAM: <strong><?php echo e(number_format($vps['ram'], 1)); ?>%</strong></div>
                            <div>Streams: <strong><?php echo e($vps['streams']); ?></strong></div>
                            <div>Updated: <?php echo e($vps['updated']); ?></div>
                        </div>
                        
                        <?php if($vps['cpu'] > 90): ?>
                            <div class="mt-2 text-red-600 font-semibold">🚨 OVERLOADED</div>
                        <?php elseif($vps['cpu'] > 70): ?>
                            <div class="mt-2 text-yellow-600 font-semibold">⚠️ HIGH LOAD</div>
                        <?php else: ?>
                            <div class="mt-2 text-green-600 font-semibold">✅ HEALTHY</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <!-- Recent Streams -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">📺 Recent Streams</h2>
            <?php
                $recentStreams = \App\Models\StreamConfiguration::with('user', 'vpsServer')
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get();
            ?>

            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-4 py-2">ID</th>
                        <th class="border border-gray-300 px-4 py-2">Title</th>
                        <th class="border border-gray-300 px-4 py-2">Status</th>
                        <th class="border border-gray-300 px-4 py-2">VPS</th>
                        <th class="border border-gray-300 px-4 py-2">User</th>
                        <th class="border border-gray-300 px-4 py-2">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $recentStreams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="border border-gray-300 px-4 py-2">#<?php echo e($stream->id); ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?php echo e(Str::limit($stream->title, 25)); ?></td>
                            <td class="border border-gray-300 px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs
                                    <?php if($stream->status === 'STREAMING'): ?> bg-green-100 text-green-800
                                    <?php elseif($stream->status === 'PENDING'): ?> bg-yellow-100 text-yellow-800
                                    <?php elseif($stream->status === 'STARTING'): ?> bg-blue-100 text-blue-800
                                    <?php elseif($stream->status === 'ERROR'): ?> bg-red-100 text-red-800
                                    <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?>">
                                    <?php echo e($stream->status); ?>

                                </span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2"><?php echo e($stream->vpsServer->name ?? 'N/A'); ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?php echo e($stream->user->name ?? 'N/A'); ?></td>
                            <td class="border border-gray-300 px-4 py-2"><?php echo e($stream->updated_at->format('H:i:s')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">🔧 Test Actions</h2>
            <div class="space-x-4">
                <a href="<?php echo e(url('/test/queue-monitor')); ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">🔄 Refresh</a>
                <a href="<?php echo e(url('/test/process-queue')); ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">⚡ Process Queue</a>
                <a href="<?php echo e(url('/admin/streams')); ?>" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">📺 Manage Streams</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH D:\laragon\www\ezstream\resources\views\test\queue-monitor.blade.php ENDPATH**/ ?>