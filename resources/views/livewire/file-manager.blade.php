<div>
    <!-- Toast notifications -->
    <div x-data="{ showSuccess: false, showError: false, message: '' }"
         x-on:show-success.window="showSuccess = true; message = $event.detail.message; setTimeout(() => showSuccess = false, 3000)"
         x-on:show-error.window="showError = true; message = $event.detail.message; setTimeout(() => showError = false, 5000)">
        
        <!-- Success Toast -->
        <div x-show="showSuccess" x-transition
             class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span x-text="message"></span>
            </div>
        </div>
        
        <!-- Error Toast -->
        <div x-show="showError" x-transition
             class="fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span x-text="message"></span>
            </div>
        </div>
    </div>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session()->has('message'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p>{{ session('message') }}</p>
                </div>
            @endif

            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Dung Lượng Lưu Trữ</h3>
                <div class="mt-2">
                    @if(auth()->user()->isAdmin())
                        <div class="flex items-center space-x-2">
                            <div class="flex-1 bg-gradient-to-r from-green-400 to-green-600 h-4 rounded-full"></div>
                            <span class="text-sm font-medium text-green-600 dark:text-green-400">Admin - Không giới hạn</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Đã sử dụng: {{ \Illuminate\Support\Number::fileSize($storageUsage, precision: 2) }}
                        </p>
                    @elseif($storageLimit > 0)
                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700">
                            @php
                                $usagePercent = ($storageUsage / $storageLimit) * 100;
                            @endphp
                            <div class="bg-blue-600 h-4 rounded-full" style="width: {{ $usagePercent }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Đã sử dụng {{ \Illuminate\Support\Number::fileSize($storageUsage, precision: 2) }} / {{ \Illuminate\Support\Number::fileSize($storageLimit, precision: 2) }}
                        </p>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Bạn chưa có gói dịch vụ nào với dung lượng lưu trữ. Vui lòng đăng ký gói để upload file.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Upload Section -->
            @if($canUpload)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📤 Tải Video Lên Google Drive</h2>
                        
                        <!-- Benefits Notice -->
                        <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-md p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Cách thức hoạt động
                                    </h3>
                                    <ul class="mt-1 text-sm text-blue-700 dark:text-blue-300 list-disc list-inside">
                                        <li>File được upload lên Google Drive để lưu trữ an toàn</li>
                                        <li>Khi bắt đầu stream, VPS sẽ tự động tải file về</li>
                                        <li>Khi kết thúc stream, file sẽ được xóa khỏi VPS ngay lập tức</li>
                                        <li>Tiết kiệm tối đa dung lượng VPS và chi phí</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Status Messages -->
                        @if($uploadStatus === 'success')
                            <div class="mb-4 bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800">{{ $uploadMessage }}</p>
                                        <button wire:click="clearStatus" class="mt-2 text-sm text-green-600 hover:text-green-500">Đóng</button>
                                    </div>
                                </div>
                            </div>
                        @elseif($uploadStatus === 'error')
                            <div class="mb-4 bg-red-50 border border-red-200 rounded-md p-4">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-800">{{ $uploadMessage }}</p>
                                        <button wire:click="clearStatus" class="mt-2 text-sm text-red-600 hover:text-red-500">Đóng</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Upload Form (Direct Submit) -->
                        <form id="upload-form" enctype="multipart/form-data" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
                            @csrf
                            
                            <!-- Upload Method Selection -->
                            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <h4 class="font-medium text-yellow-800 mb-3">🚀 Chọn phương pháp upload:</h4>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="upload-method" value="direct" checked class="mr-2">
                                        <div>
                                            <span class="font-medium text-green-700">⚡ Direct Upload (Khuyến nghị)</span>
                                            <p class="text-xs text-green-600">Upload trực tiếp lên Google Drive như YouTube</p>
                                        </div>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="upload-method" value="server" class="mr-2">
                                        <div>
                                            <span class="font-medium text-blue-700">🛡️ Server Upload (An toàn)</span>
                                            <p class="text-xs text-blue-600">Upload qua server (chậm hơn nhưng đáng tin cậy)</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <label for="file-upload" class="cursor-pointer font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                    Chọn file video
                                </label>
                                hoặc kéo thả vào đây
                            </p>
                            <p class="mt-1 text-xs text-gray-500">MP4, MOV, AVI, MKV - Hỗ trợ file lớn</p>
                            <input type="file" id="file-upload" name="file" class="hidden" accept=".mp4,.mov,.avi,.mkv,video/*" required>
                            
                            <div id="file-info" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hidden">
                                <p id="file-name" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                <p id="file-size" class="text-xs text-gray-500 mt-1"></p>
                                <button type="submit" id="upload-btn"
                                        class="mt-3 w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    🚀 Upload lên Google Drive
                                </button>
                            </div>
                            
                            <div id="upload-progress" class="mt-4 hidden">
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                    <div class="flex items-center">
                                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm text-blue-700">Đang upload lên Google Drive...</span>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const fileInput = document.getElementById('file-upload');
                            const fileInfo = document.getElementById('file-info');
                            const fileName = document.getElementById('file-name');
                            const fileSize = document.getElementById('file-size');
                            const uploadForm = document.getElementById('upload-form');
                            const uploadProgress = document.getElementById('upload-progress');
                            const uploadBtn = document.getElementById('upload-btn');

                            fileInput.addEventListener('change', function(e) {
                                const file = e.target.files[0];
                                if (file) {
                                    fileName.textContent = `File đã chọn: ${file.name}`;
                                    fileSize.textContent = `Kích thước: ${formatFileSize(file.size)}`;
                                    fileInfo.classList.remove('hidden');
                                    
                                    // Show chunked upload info for large files
                                    if (file.size > 50 * 1024 * 1024) { // > 50MB
                                        fileSize.textContent += ' - Sẽ sử dụng chunked upload để tối ưu tốc độ';
                                    }
                                } else {
                                    fileInfo.classList.add('hidden');
                                }
                            });

                            uploadForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                const file = fileInput.files[0];
                                if (!file) {
                                    alert('Vui lòng chọn file');
                                    return;
                                }

                                // Use chunked upload for files > 10MB
                                if (file.size > 10 * 1024 * 1024) {
                                    uploadFileChunked(file);
                                } else {
                                    uploadFileNormal(file);
                                }
                            });

                            // Normal upload for small files
                            function uploadFileNormal(file) {
                                const formData = new FormData();
                                formData.append('file', file);
                                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                                // Show progress
                                showUploadProgress('Đang upload...', 0);
                                uploadBtn.disabled = true;

                                fetch('/file/upload', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: formData,
                                    credentials: 'same-origin'
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        return response.text().then(text => {
                                            try {
                                                const errorData = JSON.parse(text);
                                                throw new Error(errorData.message || `HTTP ${response.status}`);
                                            } catch (e) {
                                                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
                                            }
                                        });
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.status === 'success') {
                                        @this.call('uploadSuccess', data.data.file_name, data.data.file_id, data.data.file_size);
                                        resetForm();
                                    } else {
                                        @this.call('uploadError', data.message || data.error);
                                    }
                                })
                                .catch(error => {
                                    @this.call('uploadError', `Lỗi kết nối: ${error.message}`);
                                })
                                .finally(() => {
                                    hideUploadProgress();
                                });
                            }

                            // Chunked upload for large files
                            async function uploadFileChunked(file) {
                                try {
                                    showUploadProgress('Khởi tạo upload...', 0);
                                    uploadBtn.disabled = true;

                                    // Step 1: Initialize chunked upload
                                    const initResponse = await fetch('/file/chunked/init', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            fileName: file.name,
                                            fileSize: file.size,
                                            mimeType: file.type
                                        })
                                    });

                                    const initData = await initResponse.json();
                                    if (initData.status !== 'success') {
                                        throw new Error(initData.message);
                                    }

                                    const { upload_id, chunk_size } = initData;
                                    const totalChunks = Math.ceil(file.size / chunk_size);

                                    showUploadProgress(`Uploading chunks (0/${totalChunks})...`, 0);

                                    // Step 2: Upload chunks
                                    for (let i = 0; i < totalChunks; i++) {
                                        const start = i * chunk_size;
                                        const end = Math.min(start + chunk_size, file.size);
                                        const chunk = file.slice(start, end);

                                        const chunkFormData = new FormData();
                                        chunkFormData.append('upload_id', upload_id);
                                        chunkFormData.append('chunk_index', i);
                                        chunkFormData.append('chunk', chunk);

                                        const chunkResponse = await fetch('/file/chunked/upload', {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                            },
                                            body: chunkFormData
                                        });

                                        const chunkData = await chunkResponse.json();
                                        if (chunkData.status !== 'success') {
                                            throw new Error(`Chunk ${i} failed: ${chunkData.message}`);
                                        }

                                        const progress = Math.round(((i + 1) / totalChunks) * 90); // Reserve 10% for finalization
                                        showUploadProgress(`Uploading chunks (${i + 1}/${totalChunks})...`, progress);
                                    }

                                    // Step 3: Finalize upload
                                    showUploadProgress('Đang hoàn tất upload lên Google Drive...', 95);

                                    const finalizeResponse = await fetch('/file/chunked/finalize', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            upload_id: upload_id,
                                            total_chunks: totalChunks
                                        })
                                    });

                                    const finalData = await finalizeResponse.json();
                                    if (finalData.status === 'success') {
                                        showUploadProgress('Upload hoàn tất!', 100);
                                        setTimeout(() => {
                                            @this.call('uploadSuccess', finalData.data.file_name, finalData.data.file_id, file.size);
                                            resetForm();
                                        }, 1000);
                                    } else {
                                        throw new Error(finalData.message);
                                    }

                                } catch (error) {
                                    @this.call('uploadError', `Chunked upload failed: ${error.message}`);
                                    hideUploadProgress();
                                }
                            }

                            function showUploadProgress(message, percentage) {
                                uploadProgress.innerHTML = `
                                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                        <div class="flex items-center mb-2">
                                            <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-sm text-blue-700">${message}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: ${percentage}%"></div>
                                        </div>
                                        <div class="text-xs text-blue-600 mt-1">${percentage}%</div>
                                    </div>
                                `;
                                uploadProgress.classList.remove('hidden');
                            }

                            function hideUploadProgress() {
                                uploadProgress.classList.add('hidden');
                                uploadBtn.disabled = false;
                                uploadBtn.textContent = '🚀 Upload lên Google Drive';
                            }

                            function resetForm() {
                                fileInput.value = '';
                                fileInfo.classList.add('hidden');
                                hideUploadProgress();
                            }

                            function formatFileSize(bytes) {
                                if (bytes === 0) return '0 Bytes';
                                const k = 1024;
                                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                                const i = Math.floor(Math.log(bytes) / Math.log(k));
                                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                            }
                        });
                        </script>
                    </div>
                </div>
            @endif

            <!-- File Grid -->
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-4">File Của Tôi</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @forelse($files as $userFile)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gray-200 dark:bg-gray-700 h-32 flex items-center justify-center relative">
                            <svg class="w-16 h-16 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            
                            @if($userFile->disk === 'google_drive')
                                <div class="absolute top-2 right-2 bg-blue-500 text-white text-xs px-2 py-1 rounded">
                                    ☁️ GDrive
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $userFile->original_name }}">
                                {{ \Illuminate\Support\Str::limit($userFile->original_name, 25) }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Number::fileSize($userFile->size, precision: 2) }}
                            </p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @switch($userFile->status)
                                        @case('AVAILABLE') bg-green-100 text-green-800 @break
                                        @case('PENDING_TRANSFER') bg-yellow-100 text-yellow-800 @break
                                        @case('DOWNLOADING') bg-blue-100 text-blue-800 @break
                                        @case('FAILED') bg-red-100 text-red-800 @break
                                    @endswitch
                                ">
                                    @switch($userFile->status)
                                        @case('DOWNLOADING') Đang tải @break
                                        @case('PENDING_TRANSFER') Đang chuyển @break
                                        @case('AVAILABLE') Sẵn sàng @break
                                        @case('FAILED') Thất bại @break
                                        @default {{ $userFile->status }} @break
                                    @endswitch
                                </span>
                                
                                <button wire:click="deleteFile({{ $userFile->id }})"
                                        wire:confirm="Bạn có chắc chắn muốn xóa file '{{ $userFile->original_name }}'?"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        title="Xóa file">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400">Bạn chưa có file nào.</p>
                        @if(!$canUpload)
                            <p class="text-sm text-gray-400 mt-2">Vui lòng mua gói dịch vụ để upload file.</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
