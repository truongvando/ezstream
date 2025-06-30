<div>
    <!-- Delete Confirmation Modal -->
    <?php if (isset($component)) { $__componentOriginal8825625a130ec5602a26c85b5a1506a9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8825625a130ec5602a26c85b5a1506a9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal-v2','data' => ['wire:model.live' => 'showDeleteModal','maxWidth' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal-v2'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'showDeleteModal','max-width' => 'lg']); ?>
        <div class="p-6">
            <div class="flex items-center">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                        Xác nhận xóa file
                    </h3>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-500 dark:text-gray-300">
                    Bạn có chắc chắn muốn xóa file "<strong><?php echo e($deletingFileName); ?></strong>"?
                    Hành động này không thể hoàn tác.
                </p>
            </div>
            <div class="mt-6 sm:flex sm:flex-row-reverse">
                <?php if (isset($component)) { $__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.danger-button','data' => ['wire:click' => 'deleteFile(\''.e($deletingFileId).'\')','class' => 'w-full sm:w-auto sm:ml-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('danger-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'deleteFile(\''.e($deletingFileId).'\')','class' => 'w-full sm:w-auto sm:ml-3']); ?>
                    Xóa
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11)): ?>
<?php $attributes = $__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11; ?>
<?php unset($__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11)): ?>
<?php $component = $__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11; ?>
<?php unset($__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.secondary-button','data' => ['wire:click' => '$set(\'showDeleteModal\', false)','class' => 'mt-3 w-full sm:mt-0 sm:w-auto']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('secondary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => '$set(\'showDeleteModal\', false)','class' => 'mt-3 w-full sm:mt-0 sm:w-auto']); ?>
                    Hủy
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $attributes = $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $component = $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
            </div>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8825625a130ec5602a26c85b5a1506a9)): ?>
<?php $attributes = $__attributesOriginal8825625a130ec5602a26c85b5a1506a9; ?>
<?php unset($__attributesOriginal8825625a130ec5602a26c85b5a1506a9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8825625a130ec5602a26c85b5a1506a9)): ?>
<?php $component = $__componentOriginal8825625a130ec5602a26c85b5a1506a9; ?>
<?php unset($__componentOriginal8825625a130ec5602a26c85b5a1506a9); ?>
<?php endif; ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!--[if BLOCK]><![endif]--><?php if(session()->has('message')): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo e(session('message')); ?></p>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

            <?php if(session()->has('error')): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo e(session('error')); ?></p>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Dung Lượng Lưu Trữ</h3>
                <div class="mt-2">
                    <!--[if BLOCK]><![endif]--><?php if(auth()->user()->isAdmin()): ?>
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 bg-gradient-to-r from-green-400 to-green-600 h-4 rounded-full"></div>
                            <span class="text-sm font-medium text-green-600 dark:text-green-400">Admin - Không giới hạn</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Đã sử dụng: <?php echo e(\Illuminate\Support\Number::fileSize($storageUsage, precision: 2)); ?>

                        </p>
                    <?php elseif($storageLimit > 0): ?>
                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700">
                            <?php
                                $usagePercent = ($storageLimit > 0) ? ($storageUsage / $storageLimit) * 100 : 0;
                            ?>
                            <div class="bg-blue-600 h-4 rounded-full" style="width: <?php echo e($usagePercent); ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Đã sử dụng <?php echo e(\Illuminate\Support\Number::fileSize($storageUsage, precision: 2)); ?> / 
                            <!--[if BLOCK]><![endif]--><?php if($storageLimit > 0): ?>
                                <?php echo e(\Illuminate\Support\Number::fileSize($storageLimit, precision: 2)); ?>

                            <?php else: ?>
                                Không giới hạn
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Bạn chưa có gói dịch vụ nào với dung lượng lưu trữ. Vui lòng đăng ký gói để upload file.
                        </p>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            </div>

            <!-- Upload Section -->
            <!--[if BLOCK]><![endif]--><?php if($canUpload): ?>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📤 Tải Video Lên</h2>
                        
                        <!-- Upload Method Selection -->
                        <div class="mb-6">
                            <div class="flex space-x-4 mb-4">
                                <button type="button" id="direct-upload-tab" 
                                        class="px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white">
                                    📤 Upload Trực Tiếp
                                </button>
                                <button type="button" id="gdrive-import-tab"
                                        class="px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    🔗 Import từ Google Drive
                                </button>
                            </div>
                        </div>

                        <!-- Direct Upload Form -->
                        <div id="direct-upload-section">
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Upload Trực Tiếp</h3>
                                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                            <p>• Phù hợp cho file nhỏ và trung bình (< 5GB)</p>
                                            <p>• Upload ngay lập tức, không cần bước trung gian</p>
                                            <p>• <strong>Lưu ý:</strong> File lớn có thể bị timeout nếu mạng chậm</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form id="upload-form"
                                  class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center"
                                  data-upload-url="<?php echo e(route('upload.stream')); ?>"
                                  data-max-width="<?php echo e($maxVideoWidth); ?>"
                                  data-max-height="<?php echo e($maxVideoHeight); ?>"
                                  onsubmit="return false;"
                            >
                                <?php echo csrf_field(); ?>

                                <div id="upload-prompt">
                                    <input type="file" id="file-input" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="video/mp4,video/x-m4v,video/*">
                                    
                                    <div class="flex flex-col items-center justify-center space-y-4">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <label for="file-input" class="cursor-pointer font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                                Chọn file video
                                            </label>
                                            hoặc kéo thả vào đây
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            MP4, MKV, MOV (Tối đa: <?php echo e(ini_get('upload_max_filesize')); ?>)
                                        </p>
                                        <!--[if BLOCK]><![endif]--><?php if(!auth()->user()->isAdmin()): ?>
                                            <p class="text-xs font-bold text-blue-500 dark:text-blue-400">
                                                Giới hạn chất lượng: <?php echo e($maxVideoWidth); ?>x<?php echo e($maxVideoHeight); ?>

                                            </p>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>

                                <!-- File Info Display -->
                                <div id="file-info" class="hidden mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <p id="file-name" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                    <p id="file-size" class="text-sm text-gray-500 dark:text-gray-400"></p>
                                    <button type="button" id="upload-btn" class="mt-3 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        📤 Upload File
                                    </button>
                                </div>

                                <!-- Upload Progress -->
                                <div id="upload-progress-container" class="hidden mt-4">
                                    <span class="text-sm font-semibold"></span>
                                </div>
                            </form>
                        </div>

                        <!-- Google Drive Import Section -->
                        <div id="gdrive-import-section" class="hidden">
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Import từ Google Drive</h3>
                                        <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                            <p><strong>Đề xuất cho file lớn (> 5GB):</strong></p>
                                            <p>• Upload file lên Google Drive của bạn trước (không giới hạn thời gian)</p>
                                            <p>• Server sẽ tải về với tốc độ cao, ổn định</p>
                                            <p>• Không lo timeout hay mất kết nối</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step-by-step Guide -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 mb-4">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">📋 Hướng dẫn từng bước:</h4>
                                <ol class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                    <li><strong>Bước 1:</strong> Upload video lên Google Drive của bạn</li>
                                    <li><strong>Bước 2:</strong> Chuột phải vào file → Chọn "Chia sẻ" → "Chia sẻ với mọi người"</li>
                                    <li><strong>Bước 3:</strong> Đặt quyền "Bất kỳ ai có liên kết đều có thể xem"</li>
                                    <li><strong>Bước 4:</strong> Copy link và dán vào ô bên dưới</li>
                                    <li><strong>Bước 5:</strong> Nhấn "Import" và chờ hệ thống tải về</li>
                                </ol>
                            </div>

                            <!-- Google Drive URL Input -->
                            <div class="space-y-4">
                                <div>
                                    <label for="gdrive-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        🔗 Link Google Drive
                                    </label>
                                    <input type="url" 
                                           id="gdrive-url" 
                                           placeholder="https://drive.google.com/file/d/FILE_ID/view?usp=sharing"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>
                                
                                <div class="flex space-x-3">
                                    <button type="button" 
                                            id="validate-gdrive-btn"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        🔍 Kiểm tra Link
                                    </button>
                                    <button type="button" 
                                            id="import-gdrive-btn"
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                            disabled>
                                        📥 Import File
                                    </button>
                                </div>

                                <!-- Validation Result -->
                                <div id="gdrive-validation-result" class="hidden"></div>
                                
                                <!-- Import Progress -->
                                <div id="gdrive-import-progress" class="hidden">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-blue-700 dark:text-blue-300">Đang import file từ Google Drive...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->



            <!-- File Grid -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">File Của Tôi</h2>
                <button wire:click="$refresh" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    🔄 Refresh
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!--[if BLOCK]><![endif]--><?php $__empty_1 = true; $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $userFile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gray-200 dark:bg-gray-700 h-32 flex items-center justify-center relative">
                            <svg class="w-16 h-16 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            
                            <div class="absolute top-2 right-2 bg-gray-500 text-white text-xs px-2 py-1 rounded">
                                ☁️ Cloud Storage
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate" title="<?php echo e($userFile->original_name); ?>">
                                <?php echo e(\Illuminate\Support\Str::limit($userFile->original_name, 25)); ?>

                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo e(\Illuminate\Support\Number::fileSize($userFile->size, precision: 2)); ?>

                            </p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php switch($userFile->status):
                                        case ('AVAILABLE'): ?> bg-green-100 text-green-800 <?php break; ?>
                                        <?php case ('PENDING_TRANSFER'): ?> bg-yellow-100 text-yellow-800 <?php break; ?>
                                        <?php case ('DOWNLOADING'): ?> bg-blue-100 text-blue-800 <?php break; ?>
                                        <?php case ('FAILED'): ?> bg-red-100 text-red-800 <?php break; ?>
                                    <?php endswitch; ?>
                                ">
                                    <!--[if BLOCK]><![endif]--><?php switch($userFile->status):
                                        case ('DOWNLOADING'): ?> Đang tải <?php break; ?>
                                        <?php case ('PENDING_TRANSFER'): ?> Đang chuyển <?php break; ?>
                                        <?php case ('AVAILABLE'): ?> Sẵn sàng <?php break; ?>
                                        <?php case ('FAILED'): ?> Thất bại <?php break; ?>
                                        <?php default: ?> <?php echo e($userFile->status); ?> <?php break; ?>
                                    <?php endswitch; ?><!--[if ENDBLOCK]><![endif]-->
                                </span>
                                
                                <button wire:click="confirmDelete(<?php echo e($userFile->id); ?>)"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 cursor-pointer"
                                        title="Xóa file">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400">Bạn chưa có file nào.</p>
                        <!--[if BLOCK]><![endif]--><?php if(!$canUpload): ?>
                            <p class="text-sm text-gray-400 mt-2">Vui lòng mua gói dịch vụ để upload file.</p>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\VPSLiveSeverControl\resources\views/livewire/file-manager.blade.php ENDPATH**/ ?>