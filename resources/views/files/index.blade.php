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
                    <div id="upload-progress" class="mt-4 hidden">
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

                                        @if($isAdmin)
                                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                👤 {{ $file->user->name ?? 'Unknown User' }} (ID: {{ $file->user_id }})
                                            </p>
                                        @endif

                                        <div class="flex items-center gap-2 mt-1">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                📦 {{ number_format($file->size / 1024 / 1024, 1) }} MB
                                            </p>
                                            <span class="text-xs px-2 py-1 rounded-full {{
                                                $file->disk === 'bunny_stream' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' :
                                                ($file->disk === 'bunny_cdn' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200')
                                            }}">
                                                {{ $file->disk === 'bunny_stream' ? '🎥 Stream' : ($file->disk === 'bunny_cdn' ? '☁️ CDN' : '💾 Server') }}
                                            </span>
                                        </div>

                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            🕒 {{ $file->created_at->diffForHumans() }}
                                        </p>

                                        @if($file->stream_video_id)
                                            <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                                🎬 Video ID: {{ Str::limit($file->stream_video_id, 20) }}
                                            </p>
                                        @endif
                                    </div>
                                    <button onclick="deleteFile({{ $file->id }}, '{{ $file->original_name }}', '{{ $file->user->name ?? 'Unknown' }}')"
                                            class="text-red-600 hover:text-red-800 text-sm ml-2">
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

        // Click to select file (only if not already handled by file-upload.js)
        if (!uploadForm.hasAttribute('data-click-initialized')) {
            uploadForm.addEventListener('click', () => fileInput.click());
            uploadForm.setAttribute('data-click-initialized', 'true');
        }

        // Listen for file upload completion events
        window.addEventListener('fileUploaded', function(event) {
            console.log('🎉 [UserFiles] File upload completed, refreshing page...', event.detail);

            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            successMessage.innerHTML = `✅ File "${event.detail.file_name}" đã upload thành công!`;
            document.body.appendChild(successMessage);

            // Auto-remove message after 3 seconds
            setTimeout(() => {
                successMessage.remove();
            }, 3000);

            // Refresh page after short delay to show new file
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        });

        // Custom upload success handler for this page
        window.uploadSuccessHandler = function(data) {
            console.log('📤 [UserFiles] Upload success handler called:', data);

            // Dispatch global event
            window.dispatchEvent(new CustomEvent('fileUploaded', {
                detail: data
            }));
        };

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



    function deleteFile(fileId, fileName, userName = null) {
        let confirmMessage = `Bạn có chắc muốn xóa file "${fileName}"?`;
        if (userName && userName !== 'Unknown') {
            confirmMessage = `Bạn có chắc muốn xóa file "${fileName}" của user "${userName}"?`;
        }

        if (!confirm(confirmMessage)) {
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

    <!-- File upload script already loaded globally -->
    @endpush
</x-app-layout>
