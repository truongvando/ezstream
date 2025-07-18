<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('File Manager') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">📊 Dung lượng lưu trữ</h3>
                    <div class="mb-2">
                        <div class="flex justify-between text-sm">
                            <span>Đã sử dụng: {{ number_format($storageUsage / 1024 / 1024 / 1024, 2) }} GB</span>
                            @if($isAdmin)
                                <span class="text-green-600 font-medium">🔓 Không giới hạn (Admin)</span>
                            @else
                                <span>Giới hạn: {{ number_format($storageLimit / 1024 / 1024 / 1024, 0) }} GB</span>
                            @endif
                        </div>
                        @if(!$isAdmin)
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(($storageUsage / $storageLimit) * 100, 100) }}%"></div>
                            </div>
                        @endif
                    </div>
                    @if(!$canUpload && !$isAdmin)
                        <p class="text-red-600 text-sm mt-2">⚠️ Bạn đã đạt giới hạn dung lượng. Vui lòng nâng cấp gói hoặc xóa bớt file.</p>
                    @endif
                </div>
            </div>

            <!-- Upload Form -->
            @if($canUpload)
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
                                @if($isAdmin)
                                    (Tối đa {{ number_format($maxFileSize / 1024 / 1024 / 1024, 0) }}GB - Admin, không giới hạn chất lượng)
                                @else
                                    @php
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
                                    @endphp
                                    (Tối đa {{ number_format($maxFileSize / 1024 / 1024 / 1024, 0) }}GB, chất lượng {{ $maxRes }})
                                @endif
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
            @endif

            <!-- Files List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">📁 Danh sách file</h3>
                    
                    @if($files->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($files as $file)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ $file->original_name }}
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ number_format($file->size / 1024 / 1024, 1) }} MB
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $file->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <button onclick="deleteFile({{ $file->id }}, '{{ $file->original_name }}')" 
                                            class="text-red-600 hover:text-red-800 text-sm">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4zM6 6v12h12V6H6zm3-2V2h6v2H9z"></path>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">Chưa có file nào</p>
                            <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Upload video đầu tiên của bạn</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
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

        // Click to select file
        uploadForm.addEventListener('click', () => fileInput.click());

        // File input change handler
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileUpload(file);
            }
        });

        // Drag and drop handlers
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
            if (file) {
                fileInput.files = e.dataTransfer.files;
                handleFileUpload(file);
            }
        });

        async function handleFileUpload(file) {
            // Validate file type - Only MP4
            if (file.type !== 'video/mp4') {
                alert('Chỉ hỗ trợ file MP4. Vui lòng chuyển đổi video sang định dạng MP4 trước khi upload.');
                resetForm();
                return;
            }

            // Validate file size based on user role
            const maxSize = {{ $maxFileSize }};
            const maxSizeGB = {{ number_format($maxFileSize / 1024 / 1024 / 1024, 0) }};
            if (file.size > maxSize) {
                alert(`File quá lớn. Tối đa ${maxSizeGB}GB.`);
                resetForm();
                return;
            }

            try {
                // Show progress
                showProgress();
                updateProgress('📋 Đang phân tích video...', 5);

                // Get video dimensions
                const videoDimensions = await getVideoDimensions(file);
                if (!videoDimensions || !videoDimensions.width || !videoDimensions.height) {
                    throw new Error('Không thể đọc thông tin video. Vui lòng kiểm tra file có hợp lệ không.');
                }

                updateProgress('📋 Đang tạo URL upload...', 10);

                // Step 1: Generate upload URL
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const uploadUrlResponse = await fetch('/api/generate-upload-url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        filename: file.name,
                        size: file.size,
                        content_type: file.type,
                        width: videoDimensions.width,
                        height: videoDimensions.height
                    })
                });

                if (!uploadUrlResponse.ok) {
                    const errorText = await uploadUrlResponse.text();
                    throw new Error(`HTTP ${uploadUrlResponse.status}: ${uploadUrlResponse.statusText}`);
                }

                const uploadUrlData = await uploadUrlResponse.json();

                if (uploadUrlData.status !== 'success') {
                    throw new Error(uploadUrlData.message || 'Failed to generate upload URL');
                }

                // Step 2: Upload to Server
                updateProgress('📤 Đang upload lên server...', 15);
                await uploadToBunny(file, uploadUrlData);

                // Step 3: Confirm upload
                updateProgress('✅ Đang xác nhận...', 95);
                const confirmResponse = await fetch('/api/confirm-upload', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        upload_token: uploadUrlData.upload_token,
                        size: file.size,
                        content_type: file.type
                    })
                });

                if (!confirmResponse.ok) {
                    const errorText = await confirmResponse.text();
                    throw new Error(`Confirm upload failed: ${confirmResponse.status} - ${errorText}`);
                }

                const confirmData = await confirmResponse.json();

                if (confirmData.status !== 'success') {
                    throw new Error(confirmData.message || 'Upload confirmation failed');
                }

                // Success!
                updateProgress('🎉 Upload hoàn tất!', 100);
                
                setTimeout(() => {
                    resetForm();
                    location.reload(); // Refresh page to show new file
                }, 2000);

            } catch (error) {
                updateProgress('❌ Lỗi: ' + error.message, 0);
                setTimeout(resetForm, 3000);
            }
        }

        async function uploadToBunny(file, uploadData) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 75) + 15;
                        updateProgress(`📤 Uploading... ${formatFileSize(e.loaded)}/${formatFileSize(e.total)}`, percent);
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        reject(new Error(`Bunny upload failed: ${xhr.status}`));
                    }
                });

                xhr.addEventListener('error', function() {
                    reject(new Error('Network error during upload'));
                });

                xhr.open('PUT', uploadData.upload_url);
                xhr.setRequestHeader('AccessKey', uploadData.access_key);
                xhr.setRequestHeader('Content-Type', file.type);
                xhr.send(file);
            });
        }

        function showProgress() {
            uploadProgress.classList.remove('hidden');
            fileInput.disabled = true;
        }

        function updateProgress(message, percent) {
            uploadStatus.textContent = message;
            progressBar.style.width = percent + '%';

            if (percent === 100) {
                progressBar.className = 'bg-green-600 h-2 rounded-full transition-all duration-300';
            } else if (percent === 0) {
                progressBar.className = 'bg-red-600 h-2 rounded-full transition-all duration-300';
            } else {
                progressBar.className = 'bg-blue-600 h-2 rounded-full transition-all duration-300';
            }
        }

        function resetForm() {
            uploadProgress.classList.add('hidden');
            fileInput.disabled = false;
            fileInput.value = '';
            progressBar.style.width = '0%';
            progressBar.className = 'bg-blue-600 h-2 rounded-full transition-all duration-300';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Get video dimensions from file
        function getVideoDimensions(file) {
            return new Promise((resolve) => {
                const video = document.createElement('video');
                video.preload = 'metadata';

                video.onloadedmetadata = function() {
                    window.URL.revokeObjectURL(video.src);
                    resolve({
                        width: video.videoWidth,
                        height: video.videoHeight,
                        duration: video.duration
                    });
                };

                video.onerror = function() {
                    resolve(null);
                };

                video.src = URL.createObjectURL(file);
            });
        }
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
    @endpush
</x-app-layout>
