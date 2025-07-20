<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            <?php echo e(__('File Manager')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">📊 Dung lượng lưu trữ</h3>
                    <div class="mb-2">
                        <div class="flex justify-between text-sm">
                            <span>Đã sử dụng: <?php echo e(number_format($storageUsage / 1024 / 1024 / 1024, 2)); ?> GB</span>
                            <?php if($isAdmin): ?>
                                <span class="text-green-600 font-medium">🔓 Không giới hạn (Admin)</span>
                            <?php else: ?>
                                <span>Giới hạn: <?php echo e(number_format($storageLimit / 1024 / 1024 / 1024, 0)); ?> GB</span>
                            <?php endif; ?>
                        </div>
                        <?php if(!$isAdmin): ?>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo e(min(($storageUsage / $storageLimit) * 100, 100)); ?>%"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if(!$canUpload && !$isAdmin): ?>
                        <p class="text-red-600 text-sm mt-2">⚠️ Bạn đã đạt giới hạn dung lượng. Vui lòng nâng cấp gói hoặc xóa bớt file.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Form -->
            <?php if($canUpload): ?>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">📤 Upload Video</h3>
                    
                    <div id="upload-form" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer">
                        <input type="file" id="file-input" accept="video/mp4,.mp4" class="hidden">
                        <div class="space-y-2">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-blue-600 hover:text-blue-500 cursor-pointer">Nhấn để chọn file</span>
                                hoặc kéo thả file vào đây
                            </p>
                            <p class="text-xs text-gray-500">
                                Chỉ hỗ trợ: <strong>MP4</strong>
                                <?php if($isAdmin): ?>
                                    (Tối đa <?php echo e(number_format($maxFileSize / 1024 / 1024 / 1024, 0)); ?>GB - Admin, không giới hạn chất lượng)
                                <?php else: ?>
                                    <?php
                                        $package = auth()->user()->currentPackage();
                                        if ($package && $package->max_video_width && $package->max_video_height) {
                                            // Simple resolution name logic without service
                                            $width = $package->max_video_width;
                                            $height = $package->max_video_height;
                                            if ($width >= 3840 && $height >= 2160) $maxRes = '4K UHD';
                                            elseif ($width >= 2560 && $height >= 1440) $maxRes = '2K QHD';
                                            elseif ($width >= 1920 && $height >= 1080) $maxRes = 'Full HD 1080p';
                                            elseif ($width >= 1280 && $height >= 720) $maxRes = 'HD 720p';
                                            else $maxRes = 'SD 480p';
                                        } else {
                                            $maxRes = 'HD 720p'; // Default
                                        }
                                    ?>
                                    (Tối đa <?php echo e(number_format($maxFileSize / 1024 / 1024 / 1024, 0)); ?>GB, chất lượng <?php echo e($maxRes); ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="upload-progress" class="hidden mt-4">
                        <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
                            <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <p id="upload-status" class="text-sm text-gray-600 dark:text-gray-400">Đang chuẩn bị...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Files List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">📁 Danh sách file</h3>
                    
                    <?php if($files->count() > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            <?php echo e($file->original_name); ?>

                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <?php echo e(number_format($file->size / 1024 / 1024, 1)); ?> MB
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo e($file->created_at->diffForHumans()); ?>

                                        </p>
                                    </div>
                                    <button onclick="deleteFile(<?php echo e($file->id); ?>, '<?php echo e($file->original_name); ?>')" 
                                            class="text-red-600 hover:text-red-800 text-sm">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4zM6 6v12h12V6H6zm3-2V2h6v2H9z"></path>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">Chưa có file nào</p>
                            <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Upload video đầu tiên của bạn</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('file-input');
        const uploadForm = document.getElementById('upload-form');
        const uploadProgress = document.getElementById('upload-progress');
        const progressBar = document.getElementById('progress-bar');
        const uploadStatus = document.getElementById('upload-status');

        if (!fileInput || !uploadForm) {
            return;
        }

        // Click to select file (only if not already handled by file-upload.js)
        if (!uploadForm.hasAttribute('data-click-initialized')) {
            uploadForm.addEventListener('click', () => fileInput.click());
            uploadForm.setAttribute('data-click-initialized', 'true');
        }

        // File input change handler is now handled by file-upload.js globally
        // Remove duplicate handler to prevent double uploads

        // Drag and drop handlers (only if not already handled)
        if (!uploadForm.hasAttribute('data-drag-initialized')) {
            uploadForm.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadForm.classList.add('border-blue-400', 'bg-blue-50');
            });

            uploadForm.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadForm.classList.remove('border-blue-400', 'bg-blue-50');
            });

            uploadForm.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadForm.classList.remove('border-blue-400', 'bg-blue-50');

                const file = e.dataTransfer.files[0];
                if (file && window.handleFileUpload) {
                    fileInput.files = e.dataTransfer.files;
                    window.handleFileUpload(file); // Use global function
                }
            });

            uploadForm.setAttribute('data-drag-initialized', 'true');
        }

        // All upload functionality is now handled by file-upload.js globally
        // No need for duplicate functions here
    });



    function deleteFile(fileId, fileName) {
        if (!confirm(`Bạn có chắc muốn xóa file "${fileName}"?`)) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        fetch('/files/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({
                file_id: fileId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Lỗi: ' + data.error);
            }
        })
        .catch(error => {
            alert('Lỗi: ' + error.message);
        });
    }
    </script>
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views/files/index.blade.php ENDPATH**/ ?>