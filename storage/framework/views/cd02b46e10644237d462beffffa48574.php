<div class="h-full bg-gray-50 dark:bg-gray-900">
    
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        Quản Lý Streams
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Tạo và quản lý các stream video của bạn
                    </p>
                </div>

                <div class="flex space-x-3">
                    
                    <button wire:click="openQuickStreamModal"
                            onclick="console.log('🚀 Quick Stream button clicked - preventing bubbling'); event.stopPropagation();"
                            class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        🚀 Quick Stream
                    </button>

                    
                    <button wire:click="create"
                            onclick="console.log('📝 Create Stream button clicked - preventing bubbling'); event.stopPropagation();"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Tạo Stream Mới
                    </button>
                </div>
            </div>

            
            <div class="flex flex-wrap gap-4">
                <!--[if BLOCK]><![endif]--><?php if(isset($isAdmin) && $isAdmin && isset($users)): ?>
                <div class="flex-1 min-w-48">
                    <select wire:model="filterUserId"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Tất cả người dùng</option>
                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                    </select>
                </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <div class="flex-1 min-w-48">
                    <select wire:model="filterStatus"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Tất cả trạng thái</option>
                        <option value="STREAMING">Đang phát</option>
                        <option value="STARTING">Đang khởi động</option>
                        <option value="STOPPING">Đang dừng</option>
                        <option value="ERROR">Lỗi</option>
                        <option value="INACTIVE">Không hoạt động</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    
    <div class="p-6 h-full overflow-y-auto">

        
        <!--[if BLOCK]><![endif]--><?php if($streams && $streams->count() > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4 auto-rows-fr">
            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $streams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div wire:key="stream-card-<?php echo e($stream->id); ?>"
                 class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col min-h-[380px]">
                
                
                <div class="p-4 border-b border-gray-200 dark:border-gray-600 flex-shrink-0">
                    <div class="flex items-start justify-between h-full">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    <?php echo e(Str::limit($stream->title, 25)); ?>

                                </h3>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300 flex-shrink-0">
                                    #<?php echo e($stream->id); ?>

                                </span>
                                <!--[if BLOCK]><![endif]--><?php if($stream->is_quick_stream): ?>
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-800 dark:text-purple-100 flex-shrink-0">
                                        ⚡️ Quick
                                    </span>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                <?php echo e($stream->description ? Str::limit($stream->description, 60) : 'Không có mô tả'); ?>

                            </p>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            <!--[if BLOCK]><![endif]--><?php if($stream->status === 'STREAMING'): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></div>
                                    Live
                                </span>
                            <?php elseif($stream->status === 'STARTING'): ?>
                                <div class="flex flex-col items-end space-y-1">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <div class="w-2 h-2 bg-blue-400 rounded-full mr-1 animate-spin"></div>
                                        Starting
                                    </span>
                                    
                                    <div class="w-20 bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                        <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                                             style="width: 0%"
                                             data-stream-progress="<?php echo e($stream->id); ?>"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"
                                          data-stream-message="<?php echo e($stream->id); ?>">Đang chuẩn bị...</span>
                                </div>
                            <?php elseif($stream->status === 'STOPPING'): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-1 animate-pulse"></div>
                                    Stopping
                                </span>
                            <?php elseif($stream->status === 'ERROR'): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    <div class="w-2 h-2 bg-red-400 rounded-full mr-1"></div>
                                    Error
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                    <div class="w-2 h-2 bg-gray-400 rounded-full mr-1"></div>
                                    Inactive
                                </span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>
                </div>

                
                <div class="p-4 flex-1 flex flex-col space-y-3">
                    
                    
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Người tạo:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate"><?php echo e(Str::limit($stream->user->name, 15)); ?></p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Nền tảng:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate flex items-center">
                                <?php echo $stream->platform_icon; ?>

                                <span class="ml-1"><?php echo e($stream->platform); ?></span>
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Máy chủ:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                <?php echo e($stream->vpsServer ? Str::limit($stream->vpsServer->name, 12) : 'Chưa gán'); ?>

                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Video:</span>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                <?php
                                    $fileCount = 0;
                                    if ($stream->video_source_path && is_array($stream->video_source_path)) {
                                        $fileCount = count($stream->video_source_path);
                                    } elseif ($stream->userFile) {
                                        $fileCount = 1;
                                    }
                                ?>
                                <?php echo e($fileCount); ?> file<?php echo e($fileCount !== 1 ? 's' : ''); ?>

                            </p>
                        </div>
                    </div>

                    
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600">
                        <div class="flex items-center space-x-2 mb-1">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium text-purple-700 dark:text-purple-300">Lịch phát</span>
                        </div>
                        <!--[if BLOCK]><![endif]--><?php if(($stream->enable_schedule ?? false) && $stream->scheduled_at): ?>
                            <div class="text-sm text-purple-600 dark:text-purple-400">
                                <?php echo e($stream->scheduled_at->format('d/m H:i')); ?>

                                <!--[if BLOCK]><![endif]--><?php if(isset($stream->scheduled_end) && $stream->scheduled_end): ?>
                                    - <?php echo e($stream->scheduled_end->format('H:i')); ?>

                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Chế độ thủ công
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    
                    <div class="p-3 rounded-lg border flex-1">
                        <!--[if BLOCK]><![endif]--><?php if($stream->status === 'ERROR' && $stream->error_message): ?>
                            <div class="bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700 rounded p-2">
                                <div class="flex items-start space-x-2">
                                    <svg class="w-3 h-3 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-xs font-medium text-red-800 dark:text-red-200">Lỗi:</h4>
                                        <p class="text-xs text-red-700 dark:text-red-300 line-clamp-2">
                                            <?php echo e(Str::limit($stream->error_message, 60)); ?>

                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif($stream->status === 'STREAMING'): ?>
                            <div class="bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700 rounded p-2">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <h4 class="text-xs font-medium text-green-800 dark:text-green-200">Hoạt động tốt</h4>
                                        <!--[if BLOCK]><![endif]--><?php if($stream->last_status_update): ?>
                                            <p class="text-xs text-green-700 dark:text-green-300">
                                                <?php echo e($stream->last_status_update->diffForHumans()); ?>

                                            </p>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            </div>
                        <?php elseif($stream->sync_notes && !in_array($stream->status, ['STREAMING'])): ?>
                            <div class="bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700 rounded p-2">
                                <div class="flex items-start space-x-2">
                                    <svg class="w-3 h-3 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-xs font-medium text-blue-800 dark:text-blue-200">Đồng bộ:</h4>
                                        <p class="text-xs text-blue-700 dark:text-blue-300 line-clamp-2">
                                            <?php echo e(Str::limit($stream->sync_notes, 50)); ?>

                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-600 rounded p-2">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <h4 class="text-xs font-medium text-gray-600 dark:text-gray-400">Chưa khởi động</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-500">Sẵn sàng để bắt đầu</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>

                
                <div class="p-3 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-b-lg flex-shrink-0">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 w-full">
                        
                        <div class="flex-1">
                            <!--[if BLOCK]><![endif]--><?php if(in_array($stream->status, ['INACTIVE', 'STOPPED', 'COMPLETED', 'PENDING'])): ?>
                                <button wire:click="startStream(<?php echo e($stream->id); ?>)" wire:loading.attr="disabled" wire:target="startStream(<?php echo e($stream->id); ?>)"
                                        class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200 disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    </svg>
                                    <?php if (isset($component)) { $__componentOriginal5c29929acf227acd7c5fa56a39e71fcc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-spinner','data' => ['wire:loading' => true,'wire:target' => 'startStream('.e($stream->id).')','size' => 'w-3 h-3','class' => 'mr-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-spinner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:loading' => true,'wire:target' => 'startStream('.e($stream->id).')','size' => 'w-3 h-3','class' => 'mr-1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc)): ?>
<?php $attributes = $__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc; ?>
<?php unset($__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c29929acf227acd7c5fa56a39e71fcc)): ?>
<?php $component = $__componentOriginal5c29929acf227acd7c5fa56a39e71fcc; ?>
<?php unset($__componentOriginal5c29929acf227acd7c5fa56a39e71fcc); ?>
<?php endif; ?>
                                    <span wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)">Bắt đầu</span>
                                    <span wire:loading wire:target="startStream(<?php echo e($stream->id); ?>)">Đang bắt đầu...</span>
                                </button>
                            <?php elseif($stream->status === 'STREAMING'): ?>
                                <button wire:click="stopStream(<?php echo e($stream->id); ?>)" wire:loading.attr="disabled" wire:target="stopStream(<?php echo e($stream->id); ?>)"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200 disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="stopStream(<?php echo e($stream->id); ?>)" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg wire:loading wire:target="stopStream(<?php echo e($stream->id); ?>)" class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="stopStream(<?php echo e($stream->id); ?>)">Dừng</span>
                                    <span wire:loading wire:target="stopStream(<?php echo e($stream->id); ?>)">Đang dừng...</span>
                                </button>
                            <?php elseif($stream->status === 'STARTING' || $stream->status === 'STOPPING'): ?>
                                <button disabled class="inline-flex items-center px-3 py-1.5 bg-gray-400 text-white text-sm font-medium rounded-md shadow-sm cursor-not-allowed">
                                    <svg class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <?php echo e($stream->status === 'STARTING' ? 'Đang khởi động...' : 'Đang dừng...'); ?>

                                </button>
                            <?php elseif($stream->status === 'ERROR'): ?>
                                <button wire:click="startStream(<?php echo e($stream->id); ?>)" wire:loading.attr="disabled" wire:target="startStream(<?php echo e($stream->id); ?>)"
                                        class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200 disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    </svg>
                                    <svg wire:loading wire:target="startStream(<?php echo e($stream->id); ?>)" class="animate-spin w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)">Bắt đầu</span>
                                    <span wire:loading wire:target="startStream(<?php echo e($stream->id); ?>)">Đang khởi động...</span>
                                </button>
                            <?php else: ?>
                                
                                <button wire:click="startStream(<?php echo e($stream->id); ?>)" wire:loading.attr="disabled" wire:target="startStream(<?php echo e($stream->id); ?>)"
                                        class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200 disabled:opacity-50">
                                    <svg wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    </svg>
                                    <?php if (isset($component)) { $__componentOriginal5c29929acf227acd7c5fa56a39e71fcc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.loading-spinner','data' => ['wire:loading' => true,'wire:target' => 'startStream('.e($stream->id).')','size' => 'w-3 h-3','class' => 'mr-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('loading-spinner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:loading' => true,'wire:target' => 'startStream('.e($stream->id).')','size' => 'w-3 h-3','class' => 'mr-1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc)): ?>
<?php $attributes = $__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc; ?>
<?php unset($__attributesOriginal5c29929acf227acd7c5fa56a39e71fcc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c29929acf227acd7c5fa56a39e71fcc)): ?>
<?php $component = $__componentOriginal5c29929acf227acd7c5fa56a39e71fcc; ?>
<?php unset($__componentOriginal5c29929acf227acd7c5fa56a39e71fcc); ?>
<?php endif; ?>
                                    <span wire:loading.remove wire:target="startStream(<?php echo e($stream->id); ?>)">Bắt đầu</span>
                                    <span wire:loading wire:target="startStream(<?php echo e($stream->id); ?>)">Đang khởi động...</span>
                                </button>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>

                        
                        <div class="flex space-x-2 flex-shrink-0">
                            <button wire:click="edit(<?php echo e($stream->id); ?>)"
                                    class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200"
                                    title="Chỉnh sửa">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Sửa
                            </button>
                            <button wire:click="confirmDelete(<?php echo e($stream->id); ?>)"
                                    class="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors duration-200"
                                    title="Xóa">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Xóa
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <div class="p-12 text-center">
                <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-600 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Chưa có Stream nào</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream mới hoặc sử dụng Quick Stream.</p>
                <div class="mt-6">
                    <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Tạo Stream Đầu Tiên
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

        
        <!--[if BLOCK]><![endif]--><?php if($streams && $streams->hasPages()): ?>
        <div class="mt-6">
            <?php echo e($streams->links()); ?>

        </div>
        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Track streams that are starting
    const startingStreams = new Set();

    // Find all STARTING streams on page load
    document.querySelectorAll('[data-stream-progress]').forEach(element => {
        const streamId = element.getAttribute('data-stream-progress');
        startingStreams.add(parseInt(streamId));
    });

    // Poll progress for starting streams
    function pollStreamProgress() {
        startingStreams.forEach(streamId => {
            fetch(`/api/stream/${streamId}/progress`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgressUI(streamId, data.data);

                        // Remove from tracking if completed
                        if (data.data.stage === 'streaming' || data.data.progress_percentage >= 100) {
                            startingStreams.delete(streamId);
                            // Refresh component after completion
                            setTimeout(() => {
                                window.Livewire.find('<?php echo e($_instance->getId()); ?>').call('$refresh');
                            }, 2000);
                        }
                    }
                })
                .catch(error => {
                    console.error(`Error fetching progress for stream ${streamId}:`, error);
                });
        });
    }

    // Update progress UI
    function updateProgressUI(streamId, progressData) {
        const progressBar = document.querySelector(`[data-stream-progress="${streamId}"]`);
        const messageElement = document.querySelector(`[data-stream-message="${streamId}"]`);

        if (progressBar) {
            progressBar.style.width = `${progressData.progress_percentage || 0}%`;
        }

        if (messageElement) {
            messageElement.textContent = progressData.message || 'Đang xử lý...';
        }
    }

    // Start polling if there are starting streams
    if (startingStreams.size > 0) {
        const pollInterval = setInterval(() => {
            if (startingStreams.size === 0) {
                clearInterval(pollInterval);
                return;
            }
            pollStreamProgress();
        }, 2000); // Poll every 2 seconds
    }

    // Listen for new streams starting
    window.addEventListener('stream-started', function(event) {
        const streamId = event.detail.streamId;
        startingStreams.add(streamId);

        // Start polling if not already running
        if (startingStreams.size === 1) {
            pollStreamProgress();
        }
    });
});
</script>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('livewire:init', () => {
    // Listen for session flash messages and show as notifications
    <!--[if BLOCK]><![endif]--><?php if(session('error')): ?>
        showNotification('error', <?php echo json_encode(session('error'), 15, 512) ?>);
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!--[if BLOCK]><![endif]--><?php if(session('success')): ?>
        showNotification('success', <?php echo json_encode(session('success'), 15, 512) ?>);
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    <!--[if BLOCK]><![endif]--><?php if(session('message')): ?>
        showNotification('info', <?php echo json_encode(session('message'), 15, 512) ?>);
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

    // Listen for limit exceeded event
    Livewire.on('show-limit-exceeded', (event) => {
        const data = event[0] || event;
        showLimitExceededAlert(data);
    });
});

function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-md transform transition-all duration-300 ${
        type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' :
        type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
        'bg-blue-100 border border-blue-400 text-blue-700'
    }`;

    notification.innerHTML = `
        <div class="flex items-start">
            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${type === 'error' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>' :
                    type === 'success' ?
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                }
            </svg>
            <div class="flex-1">
                <p class="text-sm font-medium">${message}</p>
                ${type === 'error' && message.includes('Vượt quá giới hạn') ?
                    '<a href="/billing" class="text-xs underline mt-1 inline-block">Nâng cấp gói ngay</a>' : ''
                }
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;

    // Add to page
    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Auto remove after 8 seconds (longer for error messages)
    const timeout = type === 'error' ? 8000 : 5000;
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, timeout);
}

function showLimitExceededAlert(data) {
    // Create a more prominent alert for limit exceeded
    const alertDiv = document.createElement('div');
    alertDiv.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
    alertDiv.innerHTML = `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md mx-4 shadow-xl">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-10 h-10 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="ml-3 text-lg font-medium text-gray-900 dark:text-white">Vượt quá giới hạn streams</h3>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">${data.message}</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded text-center">
                        <div class="text-lg font-bold text-red-600 dark:text-red-400">${data.current}</div>
                        <div class="text-gray-500 dark:text-gray-400">Đang chạy</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded text-center">
                        <div class="text-lg font-bold text-green-600 dark:text-green-400">${data.allowed}</div>
                        <div class="text-gray-500 dark:text-gray-400">Giới hạn</div>
                    </div>
                </div>
            </div>
            <div class="flex space-x-3">
                <button onclick="this.closest('.fixed').remove()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300 dark:hover:bg-gray-500">
                    Đóng
                </button>
                <a href="/billing" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-center">
                    Nâng cấp gói
                </a>
            </div>
        </div>
    `;

    document.body.appendChild(alertDiv);

    // Auto remove after 10 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 10000);
}
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/shared/stream-cards.blade.php ENDPATH**/ ?>