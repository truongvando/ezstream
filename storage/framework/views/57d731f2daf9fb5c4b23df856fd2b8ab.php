<?php if (isset($component)) { $__componentOriginal5f11a07a4ceb2a10b08382f3a19b4cf2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5f11a07a4ceb2a10b08382f3a19b4cf2 = $attributes; } ?>
<?php $component = App\View\Components\SidebarLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SidebarLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    <?php echo e(__('Theo dõi YouTube')); ?>

                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Theo dõi kênh YouTube đối thủ và phân tích hiệu suất
                </p>
            </div>
            <div class="flex space-x-3">
                <button onclick="openComparisonModal()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    So sánh kênh
                </button>
                <button onclick="openAIAnalysisModal()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    Phân tích AI
                </button>
                <button onclick="openAddChannelModal()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Thêm kênh
                </button>
            </div>
        </div>
     <?php $__env->endSlot(); ?>

    <!-- Search and Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <form method="GET" action="<?php echo e(route('youtube.index')); ?>" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" 
                       placeholder="Tìm kiếm theo tên kênh, handle hoặc ID..."
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
            </div>
            
            <?php if(auth()->user()->hasRole('admin') && $users->count() > 0): ?>
                <div class="md:w-48">
                    <select name="user_id" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <option value="">Tất cả users</option>
                        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($user->id); ?>" <?php echo e(request('user_id') == $user->id ? 'selected' : ''); ?>>
                                <?php echo e($user->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors duration-200">
                    Tìm kiếm
                </button>
                <?php if(request()->hasAny(['search', 'user_id'])): ?>
                    <a href="<?php echo e(route('youtube.index')); ?>" class="bg-gray-500 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition-colors duration-200">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Channels Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <?php if($channels->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Kênh
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Subscribers
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Videos
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Views
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Tăng trưởng
                            </th>
                            <?php if(auth()->user()->hasRole('admin')): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    User
                                </th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Cập nhật
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Thao tác
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $latest = $channel->snapshots->first();
                                $growth = $channel->getGrowthMetrics();
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <img class="h-12 w-12 rounded-full object-cover" 
                                                 src="<?php echo e($channel->thumbnail_url ?: 'https://via.placeholder.com/48x48?text=YT'); ?>" 
                                                 alt="<?php echo e($channel->channel_name); ?>">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                <a href="<?php echo e(route('youtube.show', $channel)); ?>" class="hover:text-blue-600 dark:hover:text-blue-400">
                                                    <?php echo e($channel->channel_name); ?>

                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo e($channel->channel_handle ?: $channel->channel_id); ?>

                                            </div>
                                            <?php if(!$channel->is_active): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 mt-1">
                                                    Tạm dừng
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <?php echo e($latest ? $latest->formatted_subscriber_count : 'N/A'); ?>

                                    </div>
                                    <?php if($growth['subscriber_growth'] != 0): ?>
                                        <div class="text-xs <?php echo e($growth['subscriber_growth'] > 0 ? 'text-green-600' : 'text-red-600'); ?>">
                                            <?php echo e($growth['subscriber_growth'] > 0 ? '+' : ''); ?><?php echo e(number_format($growth['subscriber_growth'])); ?>

                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo e($latest ? $latest->formatted_video_count : 'N/A'); ?>

                                    </div>
                                    <?php if($growth['video_growth'] != 0): ?>
                                        <div class="text-xs <?php echo e($growth['video_growth'] > 0 ? 'text-green-600' : 'text-red-600'); ?>">
                                            <?php echo e($growth['video_growth'] > 0 ? '+' : ''); ?><?php echo e($growth['video_growth']); ?>

                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo e($latest ? $latest->formatted_view_count : 'N/A'); ?>

                                    </div>
                                    <?php if($growth['view_growth'] != 0): ?>
                                        <div class="text-xs <?php echo e($growth['view_growth'] > 0 ? 'text-green-600' : 'text-red-600'); ?>">
                                            <?php echo e($growth['view_growth'] > 0 ? '+' : ''); ?><?php echo e(number_format($growth['view_growth'])); ?>

                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if($growth['growth_rate'] != 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo e($growth['growth_rate'] > 0 ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'); ?>">
                                            <?php echo e($growth['growth_rate'] > 0 ? '+' : ''); ?><?php echo e(number_format($growth['growth_rate'], 2)); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if(auth()->user()->hasRole('admin')): ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100"><?php echo e($channel->user->name); ?></div>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">
                                        <?php echo e($channel->last_synced_at ? $channel->last_synced_at->diffForHumans() : 'Chưa đồng bộ'); ?>

                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="<?php echo e(route('youtube.show', $channel)); ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            Chi tiết
                                        </a>
                                        <button onclick="syncMoreVideos(<?php echo e($channel->id); ?>)" class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 text-xs">
                                            📥 Sync Videos
                                        </button>
                                        <button onclick="toggleChannel(<?php echo e($channel->id); ?>)" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">
                                            <?php echo e($channel->is_active ? 'Tạm dừng' : 'Kích hoạt'); ?>

                                        </button>
                                        <button onclick="deleteChannel(<?php echo e($channel->id); ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            Xóa
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <?php echo e($channels->links()); ?>

            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có kênh nào</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách thêm kênh YouTube để theo dõi.</p>
                <div class="mt-6">
                    <button onclick="openAddChannelModal()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Thêm kênh đầu tiên
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Channel Modal -->
    <div id="addChannelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Thêm kênh YouTube</h3>
                <form id="addChannelForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            URL hoặc tên kênh YouTube
                        </label>
                        <input type="text" id="channelUrl" name="channel_url" required
                               placeholder="https://youtube.com/@username hoặc @username"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Hỗ trợ: URL kênh, @username, hoặc Channel ID
                        </p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddChannelModal()" 
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-200">
                            Hủy
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors duration-200">
                            Thêm kênh
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Channel Comparison Modal -->
    <div id="comparisonModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">So sánh kênh YouTube</h3>
                    <button onclick="closeComparisonModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Chọn 2-5 kênh để so sánh hiệu suất:</p>

                    <form id="comparisonForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 max-h-60 overflow-y-auto">
                            <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex items-center p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                    <input type="checkbox" name="comparison_channels[]" value="<?php echo e($channel->id); ?>" class="mr-3 comparison-checkbox">
                                    <div class="flex items-center flex-1">
                                        <img src="<?php echo e($channel->thumbnail_url); ?>" alt="<?php echo e($channel->channel_name); ?>"
                                             class="w-10 h-10 rounded-full mr-3">
                                        <div>
                                            <div class="font-medium text-sm"><?php echo e($channel->channel_name); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo e(number_format($channel->latestSnapshot()?->subscriber_count ?? 0)); ?> subscribers
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeComparisonModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                                Hủy
                            </button>
                            <button type="submit" id="compareBtn" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:opacity-50" disabled>
                                So sánh kênh
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Comparison Results -->
                <div id="comparisonResults" class="hidden mt-6">
                    <div class="border-t pt-4">
                        <h4 class="font-medium mb-4">Kết quả so sánh:</h4>
                        <div id="comparisonContent">
                            <!-- Results will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Analysis Modal -->
    <div id="aiAnalysisModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Phân tích AI cho kênh YouTube</h3>
                    <button onclick="closeAIAnalysisModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Chọn kênh để phân tích bằng AI:</p>

                    <form id="aiAnalysisForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Chọn kênh:</label>
                            <select name="channel_id" id="aiChannelSelect" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">-- Chọn kênh --</option>
                                <?php $__currentLoopData = $channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($channel->id); ?>"><?php echo e($channel->channel_name); ?> (<?php echo e(number_format($channel->latestSnapshot()?->subscriber_count ?? 0)); ?> subs)</option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                                <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">🤖 Phân tích AI toàn diện</h4>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    AI sẽ phân tích tất cả dữ liệu của kênh bao gồm: hiệu suất tổng quan, chiến lược nội dung,
                                    tăng trưởng, engagement, thời điểm đăng video tối ưu và đưa ra khuyến nghị cụ thể.
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeAIAnalysisModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                                Hủy
                            </button>
                            <button type="submit" id="analyzeBtn" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50">
                                Phân tích với AI
                            </button>
                        </div>
                    </form>
                </div>

                <!-- AI Analysis Results -->
                <div id="aiAnalysisResults" class="hidden mt-6">
                    <div class="border-t pt-4">
                        <h4 class="font-medium mb-4">Kết quả phân tích AI:</h4>
                        <div id="aiAnalysisContent" class="prose dark:prose-invert max-w-none">
                            <!-- AI results will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="aiAnalysisLoading" class="hidden mt-6 text-center py-8">
                    <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-sm shadow rounded-md text-white bg-green-500 hover:bg-green-400 transition ease-in-out duration-150 cursor-not-allowed">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        AI đang phân tích...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function openAddChannelModal() {
            document.getElementById('addChannelModal').classList.remove('hidden');
        }

        function openComparisonModal() {
            document.getElementById('comparisonModal').classList.remove('hidden');
            updateCompareButton();
        }

        function closeComparisonModal() {
            document.getElementById('comparisonModal').classList.add('hidden');
            document.getElementById('comparisonResults').classList.add('hidden');
            // Reset checkboxes
            document.querySelectorAll('.comparison-checkbox').forEach(cb => cb.checked = false);
        }

        function openAIAnalysisModal() {
            document.getElementById('aiAnalysisModal').classList.remove('hidden');
        }

        function closeAIAnalysisModal() {
            document.getElementById('aiAnalysisModal').classList.add('hidden');
            document.getElementById('aiAnalysisResults').classList.add('hidden');
            document.getElementById('aiAnalysisLoading').classList.add('hidden');
        }

        function closeAddChannelModal() {
            document.getElementById('addChannelModal').classList.add('hidden');
            document.getElementById('addChannelForm').reset();
        }

        // Comparison Modal Functions
        function updateCompareButton() {
            const checkedBoxes = document.querySelectorAll('.comparison-checkbox:checked');
            const compareBtn = document.getElementById('compareBtn');
            compareBtn.disabled = checkedBoxes.length < 2 || checkedBoxes.length > 5;
        }

        // Add channel form submission
        document.getElementById('addChannelForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            submitBtn.textContent = 'Đang thêm...';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('<?php echo e(route("youtube.store")); ?>', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeAddChannelModal();

                    // Hiển thị thông báo thành công
                    const successMsg = document.createElement('div');
                    successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                    successMsg.innerHTML = '✅ ' + data.message;
                    document.body.appendChild(successMsg);

                    // Tự động ẩn thông báo sau 3 giây
                    setTimeout(() => {
                        successMsg.remove();
                    }, 3000);

                    // Reload trang sau 2 giây để hiển thị kênh mới với videos
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi thêm kênh');
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });

        // Toggle channel active status
        async function toggleChannel(channelId) {
            if (!confirm('Bạn có chắc muốn thay đổi trạng thái kênh này?')) return;
            
            try {
                const response = await fetch(`/youtube-monitoring/${channelId}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Có lỗi xảy ra');
            }
        }

        // Sync more videos for channel
        async function syncMoreVideos(channelId) {
            if (!confirm('Bạn có muốn đồng bộ thêm videos cho kênh này? Quá trình có thể mất vài phút.')) return;

            try {
                const response = await fetch(`/youtube-monitoring/${channelId}/sync-more-videos`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_size: 50
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Hiển thị thông báo thành công
                    const successMsg = document.createElement('div');
                    successMsg.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                    successMsg.innerHTML = '📥 ' + data.message;
                    document.body.appendChild(successMsg);

                    // Tự động ẩn thông báo sau 5 giây
                    setTimeout(() => {
                        successMsg.remove();
                    }, 5000);
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Có lỗi xảy ra khi đồng bộ videos');
            }
        }

        // Delete channel
        async function deleteChannel(channelId) {
            if (!confirm('Bạn có chắc muốn xóa kênh này? Hành động này không thể hoàn tác.')) return;
            
            try {
                const response = await fetch(`/youtube-monitoring/${channelId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            } catch (error) {
                alert('Có lỗi xảy ra');
            }
        }

        // Close modal when clicking outside
        document.getElementById('addChannelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddChannelModal();
            }
        });

        // AI Analysis Functions
        function analyzeChannelWithAI(channelId) {
            // Show loading state
            document.getElementById('aiAnalysisLoading').classList.remove('hidden');
            document.getElementById('aiAnalysisResults').classList.add('hidden');

            // Make API call
            fetch('<?php echo e(route("youtube.ai.analyze")); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    channel_id: channelId
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('aiAnalysisLoading').classList.add('hidden');

                if (data.success) {
                    displayAIAnalysisResults(data.data);
                } else {
                    document.getElementById('aiAnalysisContent').innerHTML = '<div class="text-red-600">Lỗi: ' + data.message + '</div>';
                    document.getElementById('aiAnalysisResults').classList.remove('hidden');
                }
            })
            .catch(error => {
                document.getElementById('aiAnalysisLoading').classList.add('hidden');
                console.error('Error:', error);
                document.getElementById('aiAnalysisContent').innerHTML = '<div class="text-red-600">Có lỗi xảy ra khi phân tích với AI</div>';
                document.getElementById('aiAnalysisResults').classList.remove('hidden');
            });
        }

        function displayAIAnalysisResults(data) {
            document.getElementById('aiAnalysisResults').classList.remove('hidden');

            let html = '<div class="space-y-6">';

            // Channel info header
            html += '<div class="flex items-center space-x-4 p-4 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-900/20 dark:to-blue-900/20 rounded-lg border">';
            html += '<img src="' + data.channel.thumbnail + '" alt="' + data.channel.name + '" class="w-16 h-16 rounded-full">';
            html += '<div>';
            html += '<h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">' + data.channel.name + '</h3>';
            html += '<p class="text-sm text-gray-600 dark:text-gray-400">Phân tích được tạo lúc: ' + data.generated_at + '</p>';
            html += '</div>';
            html += '</div>';

            // AI Analysis content
            html += '<div class="bg-white dark:bg-gray-800 rounded-lg border p-6">';
            html += '<h4 class="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">🤖 Phân tích AI toàn diện</h4>';

            // Format the AI response with proper line breaks and styling
            let formattedAnalysis = data.analysis;

            // Convert \n to <br> first
            formattedAnalysis = formattedAnalysis.replace(/\n/g, '<br>');

            // Format main headers (## Section) with beautiful cards
            formattedAnalysis = formattedAnalysis.replace(/## ([^<]+)/g,
                '<div class="mt-6 mb-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border-l-4 border-blue-500">' +
                '<h3 class="text-lg font-bold text-blue-700 dark:text-blue-300 flex items-center">' +
                '<span class="mr-2">📊</span>$1</h3>' +
                '</div>'
            );

            // Format bullet points (- text) with nice icons
            formattedAnalysis = formattedAnalysis.replace(/- ([^<]+?)(<br>|$)/g,
                '<div class="ml-6 mb-3 flex items-start">' +
                '<span class="text-green-500 mr-3 mt-1 font-bold">▸</span>' +
                '<span class="text-gray-700 dark:text-gray-300 leading-relaxed">$1</span>' +
                '</div>'
            );

            // Format bold text (**text**) with highlight
            formattedAnalysis = formattedAnalysis.replace(/\*\*([^*]+)\*\*/g,
                '<strong class="font-semibold text-gray-900 dark:text-gray-100 bg-yellow-100 dark:bg-yellow-900/30 px-1 rounded">$1</strong>'
            );

            // Format numbered lists (1. text) with badges
            formattedAnalysis = formattedAnalysis.replace(/(\d+\.) ([^<]+?)(<br>|$)/g,
                '<div class="ml-6 mb-3 flex items-start">' +
                '<span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5 flex-shrink-0">$1</span>' +
                '<span class="text-gray-700 dark:text-gray-300 leading-relaxed">$2</span>' +
                '</div>'
            );

            // Clean up and add spacing
            formattedAnalysis = formattedAnalysis.replace(/<br><br>/g, '<div class="mb-4"></div>');
            formattedAnalysis = formattedAnalysis.replace(/<br>/g, '<div class="mb-2"></div>');

            html += '<div class="space-y-2">' + formattedAnalysis + '</div>';
            html += '</div>';

            // Show only analysis time
            html += '<div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">';
            html += '<div class="text-sm text-gray-600 dark:text-gray-400">';
            html += '🕒 Thời gian phân tích: <span class="font-medium">' + data.generated_at + '</span>';
            html += '</div>';
            html += '</div>';

            html += '</div>';

            document.getElementById('aiAnalysisContent').innerHTML = html;
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Comparison checkboxes
            document.querySelectorAll('.comparison-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateCompareButton);
            });

            // Comparison form submission
            document.getElementById('comparisonForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const checkedBoxes = document.querySelectorAll('.comparison-checkbox:checked');
                const channelIds = Array.from(checkedBoxes).map(cb => cb.value);

                if (channelIds.length < 2) {
                    alert('Vui lòng chọn ít nhất 2 kênh để so sánh');
                    return;
                }

                compareChannels(channelIds);
            });

            // AI Analysis form submission
            document.getElementById('aiAnalysisForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const channelId = document.getElementById('aiChannelSelect').value;

                if (!channelId) {
                    alert('Vui lòng chọn kênh để phân tích');
                    return;
                }

                analyzeChannelWithAI(channelId);
            });
        });

        // Comparison Functions
        function compareChannels(channelIds) {
            // Show loading state
            document.getElementById('comparisonResults').classList.remove('hidden');
            document.getElementById('comparisonContent').innerHTML = '<div class="text-center py-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto"></div><p class="mt-2 text-sm text-gray-600">Đang so sánh kênh...</p></div>';

            // Make API call
            fetch('<?php echo e(route("youtube.comparison.compare")); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    channel_ids: channelIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayComparisonResults(data.data);
                } else {
                    document.getElementById('comparisonContent').innerHTML = '<div class="text-red-600">Lỗi: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('comparisonContent').innerHTML = '<div class="text-red-600">Có lỗi xảy ra khi so sánh kênh</div>';
            });
        }

        function displayComparisonResults(data) {
            let html = '<div class="space-y-6">';

            // Performance Leaders Section
            html += '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">';

            const topKenh = data.metrics.top_kenh;
            const leaderCards = [
                { title: '👑 Nhiều Sub Nhất', channel: topKenh.kenh_nhieu_sub_nhat, color: 'blue' },
                { title: '🚀 Tăng Trưởng Nhanh', channel: topKenh.kenh_tang_truong_nhanh_nhat, color: 'green' },
                { title: '💬 Tương Tác Cao', channel: topKenh.kenh_tuong_tac_cao_nhat, color: 'purple' },
                { title: '🎬 Sản Xuất Nhiều', channel: topKenh.kenh_san_xuat_nhieu_nhat, color: 'orange' },
                { title: '⚡ Hiệu Quả Cao', channel: topKenh.kenh_hieu_qua_cao_nhat, color: 'red' }
            ];

            leaderCards.forEach(card => {
                html += '<div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border">';
                html += '<h5 class="font-medium text-gray-800 dark:text-gray-200 text-xs mb-1">' + card.title + '</h5>';
                html += '<p class="text-sm font-semibold text-gray-600 dark:text-gray-400">' + (card.channel ? card.channel.channel_name : 'N/A') + '</p>';
                html += '</div>';
            });

            html += '</div>';

            // Tổng quan chỉ số nâng cao
            html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">';
            const chiSoNangCao = data.metrics.chi_so_nang_cao;
            html += '<div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg"><h5 class="font-medium text-blue-800 dark:text-blue-200 mb-2">TB Tương Tác</h5><p class="text-lg font-bold text-blue-600">' + chiSoNangCao.tb_ty_le_tuong_tac.toFixed(3) + '%</p></div>';
            html += '<div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg"><h5 class="font-medium text-green-800 dark:text-green-200 mb-2">TB Hiệu Quả</h5><p class="text-lg font-bold text-green-600">' + new Intl.NumberFormat().format(Math.round(chiSoNangCao.tb_hieu_qua_subscriber)) + '</p></div>';
            html += '<div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg"><h5 class="font-medium text-purple-800 dark:text-purple-200 mb-2">TB Sản Xuất</h5><p class="text-lg font-bold text-purple-600">' + chiSoNangCao.tb_toc_do_san_xuat.toFixed(1) + ' video/năm</p></div>';
            html += '<div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800"><h5 class="font-medium text-orange-800 dark:text-orange-200 mb-1">Doanh Thu Hàng Tháng</h5><p class="text-lg font-bold text-orange-600">$' + new Intl.NumberFormat().format(chiSoNangCao.tong_doanh_thu_hang_thang) + '</p><p class="text-xs text-orange-600 mt-1">🤖 RPM từ AI (55% revenue share)</p></div>';
            html += '</div>';

            // Bảng so sánh chi tiết
            html += '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"><thead class="bg-gray-50 dark:bg-gray-900"><tr>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Kênh</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Chủ Đề</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subscribers</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tương Tác</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">RPM</th>';
            html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Thu Nhập/Tháng</th>';
            html += '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

            // Sử dụng dữ liệu kênh từ response
            Object.values(data.metrics.channel_metrics).forEach(channelData => {
                const channel = channelData.kenh;

                // Map chủ đề sang tiếng Việt
                const chuDeMap = {
                    'tai_chinh': 'Tài chính',
                    'cong_nghe': 'Công nghệ',
                    'giao_duc': 'Giáo dục',
                    'giai_tri': 'Giải trí',
                    'game': 'Game',
                    'tre_em': 'Trẻ em',
                    'nhac': 'Âm nhạc'
                };

                const tenChuDe = chuDeMap[channelData.chu_de_kenh] || 'Giải trí';

                html += '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">';
                html += '<td class="px-3 py-2"><div class="flex items-center"><img class="h-8 w-8 rounded-full mr-2" src="' + channel.thumbnail_url + '" alt="' + channel.channel_name + '"><div class="text-sm font-medium">' + channel.channel_name + '</div></div></td>';
                html += '<td class="px-3 py-2"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">' + tenChuDe + '</span></td>';
                html += '<td class="px-3 py-2 text-sm">' + new Intl.NumberFormat().format(channelData.subscribers) + '</td>';
                html += '<td class="px-3 py-2 text-sm">' + channelData.ty_le_tuong_tac.toFixed(3) + '%</td>';

                // RPM với indicator nguồn
                const rpmSource = channelData.rpm_source || 'auto_category';
                const rpmIcon = rpmSource === 'ai_analysis' ? '🤖' : '📊';
                const rpmColor = rpmSource === 'ai_analysis' ? 'text-blue-600' : 'text-green-600';
                html += '<td class="px-3 py-2 text-sm font-medium ' + rpmColor + '">' + rpmIcon + ' $' + channelData.rpm_thuc_te + '</td>';

                html += '<td class="px-3 py-2 text-sm font-bold text-orange-600">$' + new Intl.NumberFormat().format(channelData.doanh_thu_hang_thang) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            // Thêm giải thích chi tiết về RPM theo chủ đề
            html += '<div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">';
            html += '<h5 class="font-medium text-yellow-800 dark:text-yellow-200 mb-3">📊 Cách tính doanh thu hàng tháng:</h5>';
            html += '<div class="text-sm text-yellow-700 dark:text-yellow-300 space-y-2">';
            html += '<p><strong>Công thức:</strong> Views hàng tháng × RPM (đã bao gồm 55% revenue share)</p>';

            html += '<div class="mt-3"><strong>Nguồn RPM:</strong></div>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2 text-xs">';
            html += '<div class="bg-blue-100 dark:bg-blue-900 p-2 rounded border">🤖 <strong>AI Analysis:</strong> RPM từ phân tích AI (ưu tiên)</div>';
            html += '<div class="bg-green-100 dark:bg-green-900 p-2 rounded border">📊 <strong>Auto Category:</strong> RPM theo phân loại tự động</div>';
            html += '</div>';

            html += '<div class="mt-3"><strong>RPM theo chủ đề (USD/1000 views - 55% revenue share):</strong></div>';
            html += '<div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2 text-xs">';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">🏦 Tài chính: $0.55-2.75</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">💻 Công nghệ: $0.44-2.20</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">📚 Giáo dục: $0.39-1.65</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">🎬 Giải trí: $0.11-0.44</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">🎮 Game: $0.11-0.44</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">👶 Trẻ em: $0.11-0.33</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">🎵 Âm nhạc: $0.06-0.22</div>';
            html += '<div class="bg-white dark:bg-gray-800 p-2 rounded border">📱 Shorts: $0.05-0.15</div>';
            html += '</div>';

            html += '<div class="mt-3"><strong>Mùa quảng cáo:</strong></div>';
            html += '<div class="text-xs mt-1">';
            html += '<span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded mr-2">T11-T12: RPM cao gấp đôi</span>';
            html += '<span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded mr-2">T1-T2: RPM thấp nhất</span>';
            html += '</div>';

            html += '<p class="mt-3 text-xs"><strong>⚠️ Lưu ý:</strong> Đây chỉ là ước tính dựa trên dữ liệu thị trường VN. Doanh thu thực tế phụ thuộc vào nhiều yếu tố khác như đối tượng khán giả, chất lượng nội dung, thời điểm đăng video...</p>';
            html += '</div></div>';

            // Thị phần subscribers
            html += '<div class="mt-6"><h5 class="font-medium mb-4">Thị Phần Subscribers:</h5>';
            html += '<div class="space-y-2">';
            Object.entries(data.metrics.phan_tich_canh_tranh.ty_le_thi_phan).forEach(([channelId, share]) => {
                const channelData = data.metrics.channel_metrics[channelId];
                if (channelData) {
                    html += '<div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded">';
                    html += '<span class="text-sm font-medium">' + channelData.kenh.channel_name + '</span>';
                    html += '<span class="text-sm text-gray-600">' + share.toFixed(1) + '%</span>';
                    html += '</div>';
                }
            });
            html += '</div></div>';

            html += '</div>';

            document.getElementById('comparisonContent').innerHTML = html;
        }
    </script>
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5f11a07a4ceb2a10b08382f3a19b4cf2)): ?>
<?php $attributes = $__attributesOriginal5f11a07a4ceb2a10b08382f3a19b4cf2; ?>
<?php unset($__attributesOriginal5f11a07a4ceb2a10b08382f3a19b4cf2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5f11a07a4ceb2a10b08382f3a19b4cf2)): ?>
<?php $component = $__componentOriginal5f11a07a4ceb2a10b08382f3a19b4cf2; ?>
<?php unset($__componentOriginal5f11a07a4ceb2a10b08382f3a19b4cf2); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views/youtube-monitoring/index.blade.php ENDPATH**/ ?>