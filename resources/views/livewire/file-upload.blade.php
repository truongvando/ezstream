<div x-data="{
    init() {
        // Listen for global file upload events
        window.addEventListener('fileUploaded', (event) => {
            console.log('🎉 [FileUpload] Global fileUploaded event received:', event.detail);
            // Trigger Livewire refresh
            if (window.Livewire) {
                window.Livewire.dispatch('fileUploaded', event.detail);
            }
        });
    }
}">
    <!-- Delete Confirmation Modal -->
    <x-modal-v2 wire:model.live="showDeleteModal" max-width="lg">
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
                    Bạn có chắc chắn muốn xóa file "<strong>{{ $deletingFileName }}</strong>"?
                    Hành động này không thể hoàn tác.
                </p>
            </div>
            <div class="mt-6 sm:flex sm:flex-row-reverse">
                <x-danger-button wire:click="deleteFile" class="w-full sm:w-auto sm:ml-3">
                    Xóa
                </x-danger-button>
                <x-secondary-button wire:click="$set('showDeleteModal', false)" class="mt-3 w-full sm:mt-0 sm:w-auto">
                    Hủy
                </x-secondary-button>
            </div>
        </div>
    </x-modal-v2>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">📁 Quản Lý File</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Upload và quản lý video của bạn</p>
            </div>

            @if (session()->has('message'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p>{{ session('message') }}</p>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">💾 Dung Lượng Lưu Trữ</h3>
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
                                $usagePercent = ($storageLimit > 0) ? ($storageUsage / $storageLimit) * 100 : 0;
                            @endphp
                            <div class="bg-blue-600 h-4 rounded-full" style="width: {{ $usagePercent }}%"></div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Đã sử dụng {{ \Illuminate\Support\Number::fileSize($storageUsage, precision: 2) }} / 
                            {{ \Illuminate\Support\Number::fileSize($storageLimit, precision: 2) }}
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
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📤 Upload Video</h2>
                        
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
                                        <p>• File sẽ được upload trực tiếp lên server</p>
                                        <p>• Không qua server trung gian, tốc độ nhanh và ổn định</p>
                                        <p>• Hỗ trợ file lớn đến 20GB</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Form -->
                        <div id="upload-form" class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 transition-colors">
                            <input type="file" id="file-input" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="video/*">
                            
                            <div class="flex flex-col items-center justify-center space-y-4">
                                <svg class="mx-auto h-16 w-16 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <div>
                                    <p class="text-xl font-medium text-gray-900 dark:text-gray-100">
                                        Chọn file video hoặc kéo thả vào đây
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Hỗ trợ: MP4, AVI, MOV, WMV, FLV, WEBM, MKV
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Tối đa: 20GB
                                    </p>
                                </div>
                            </div>

                            <!-- Progress Bar (Hidden by default) -->
                            <div id="upload-progress" class="hidden mt-6">
                                <div class="bg-gray-200 rounded-full h-2">
                                    <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                                <p id="upload-status" class="text-sm text-gray-600 dark:text-gray-400 mt-2">Đang chuẩn bị...</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- File List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">📂 File Của Tôi</h2>
                        <button wire:click="$refresh"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            🔄 Refresh
                        </button>
                    </div>

                    @if($files->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                            @foreach($files as $file)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex-shrink-0">
                                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <button wire:click="confirmDelete({{ $file->id }})"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                title="Xóa file">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <h3 class="font-medium text-gray-900 dark:text-gray-100 text-sm mb-2 truncate" title="{{ $file->original_name }}">
                                        {{ $file->original_name }}
                                    </h3>

                                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <p>📦 {{ \Illuminate\Support\Number::fileSize($file->size) }}</p>
                                        <p>📅 {{ $file->created_at->format('d/m/Y H:i') }}</p>
                                        <p>
                                            @if($file->disk === 'bunny_cdn')
                                                <span class="text-green-600">☁️ Server</span>
                                            @elseif($file->disk === 'google_drive')
                                                <span class="text-blue-600">💾 Google Drive</span>
                                            @else
                                                <span class="text-gray-600">💾 Local</span>
                                            @endif
                                        </p>
                                        <p>
                                            @if($file->status === 'ready')
                                                <span class="text-green-600">✅ Sẵn sàng</span>
                                            @elseif($file->status === 'uploading')
                                                <span class="text-yellow-600">⏳ Đang tải</span>
                                            @elseif($file->status === 'processing')
                                                <span class="text-blue-600">🔄 Đang xử lý</span>
                                            @else
                                                <span class="text-red-600">❌ Lỗi</span>
                                            @endif
                                        </p>
                                    </div>

                                    {{-- File viewing temporarily disabled --}}
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-lg mt-4">Chưa có file nào</p>
                            @if(!$canUpload)
                                <p class="text-sm text-gray-400 mt-2">Vui lòng mua gói dịch vụ để upload file.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

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
        // Validate file type
        if (!file.type.startsWith('video/')) {
            alert('Vui lòng chỉ chọn file video.');
            resetForm();
            return;
        }

        // Validate file size (20GB max)
        const maxSize = 20 * 1024 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('File quá lớn. Tối đa 20GB.');
            resetForm();
            return;
        }

        try {
            // Show progress
            showProgress();
            updateProgress('📋 Đang tạo URL upload...', 5);

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
                    content_type: file.type
                })
            });

            if (!uploadUrlResponse.ok) {
                const errorData = await uploadUrlResponse.json().catch(() => ({ error: 'Lỗi không xác định' }));

                // Show detailed error modal if available
                if (errorData.reason && errorData.details && errorData.solutions && window.showDetailedErrorModal) {
                    window.showDetailedErrorModal(errorData);
                    resetForm();
                    return;
                }

                throw new Error(errorData.error || errorData.message || `HTTP ${uploadUrlResponse.status}: ${uploadUrlResponse.statusText}`);
            }

            const uploadUrlData = await uploadUrlResponse.json();

            if (uploadUrlData.status !== 'success') {
                throw new Error(uploadUrlData.message || 'Failed to generate upload URL');
            }

            // Step 2: Upload to Server
            updateProgress('📤 Đang upload lên server...', 10);
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

            // Enhanced notification system for file upload page
            let notificationSent = false;

            // Method 1: Livewire dispatch (primary method for this page)
            if (window.Livewire) {
                try {
                    // Debug: Log confirmData structure
                    console.log('🔍 confirmData structure:', confirmData);

                    const eventData = {
                        file_name: file.name,
                        file_id: confirmData.file_id || confirmData.id || confirmData.file?.id,
                        file_size: file.size
                    };

                    console.log('📤 Dispatching Livewire event with data:', eventData);

                    window.Livewire.dispatch('fileUploaded', eventData);
                    notificationSent = true;
                    console.log('✅ Livewire fileUploaded event dispatched successfully');
                } catch (e) {
                    console.error('❌ Livewire dispatch failed:', e);
                }
            }

            // Method 2: Direct Livewire component refresh (more reliable)
            if (!notificationSent && window.Livewire) {
                try {
                    // Find the FileUpload component and refresh it directly
                    const fileUploadComponent = window.Livewire.find('file-upload');
                    if (fileUploadComponent) {
                        fileUploadComponent.$refresh();
                        console.log('✅ Direct component refresh triggered');
                        notificationSent = true;
                    } else {
                        // Try to refresh all components
                        window.Livewire.rescan();
                        console.log('✅ Livewire rescan triggered');
                        notificationSent = true;
                    }
                } catch (e) {
                    console.warn('⚠️ Direct refresh failed:', e);
                }
            }

            // Method 3: Global event (for any other components listening)
            if (!notificationSent) {
                try {
                    window.dispatchEvent(new CustomEvent('fileUploaded', {
                        detail: {
                            file_name: file.name,
                            file_id: confirmData.file_id || confirmData.id || confirmData.file?.id,
                            file_size: file.size
                        }
                    }));
                    notificationSent = true;
                    console.log('✅ Global fileUploaded event dispatched');
                } catch (e) {
                    console.warn('⚠️ Global event failed:', e);
                }
            }

            // Method 3: Force refresh as fallback (should rarely be needed)
            if (!notificationSent) {
                console.log('📄 No notification method worked, forcing page refresh');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }

            setTimeout(resetForm, 2000);

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
                    const percent = Math.round((e.loaded / e.total) * 80) + 10;
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
        uploadStatus.textContent = 'Đang chuẩn bị...';
        progressBar.className = 'bg-blue-600 h-2 rounded-full transition-all duration-300';
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
