<div>
    <!-- Delete Confirmation Modal -->
    <div x-data="{ 
        showDeleteModal: false, 
        deleteFileId: null, 
        deleteFileName: '',
        showSuccess: false, 
        showError: false, 
        message: '' 
    }"
         x-on:show-success.window="showSuccess = true; message = $event.detail.message; setTimeout(() => showSuccess = false, 3000)"
         x-on:show-error.window="showError = true; message = $event.detail.message; setTimeout(() => showError = false, 5000)"
         x-on:show-delete-modal.window="showDeleteModal = true; deleteFileId = $event.detail.fileId; deleteFileName = $event.detail.fileName">
        
        <!-- Delete Modal -->
        <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Xóa file</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Bạn có chắc chắn muốn xóa file "<span x-text="deleteFileName" class="font-semibold"></span>"? 
                                    Hành động này không thể hoàn tác.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button @click="confirmDelete(deleteFileId, deleteFileName); showDeleteModal = false" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Xóa
                        </button>
                        <button @click="showDeleteModal = false" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Hủy
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
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

            @if (session()->has('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p>{{ session('error') }}</p>
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
                                        <li>File được upload stream qua server Laravel</li>
                                        <li>Server tự động upload lên Google Drive ngay lập tức</li>
                                        <li>Không lưu file tạm trên server</li>
                                        <li>Không còn lỗi CORS và hỗ trợ file lớn (lên đến 2GB)</li>
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

                        <!-- Upload Form (Simple Streaming) -->
                        <form id="upload-form" enctype="multipart/form-data" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
                            @csrf

                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <label for="file-upload" class="cursor-pointer font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                    Chọn file video
                                </label>
                                hoặc kéo thả vào đây
                            </p>
                            <p class="mt-1 text-xs text-gray-500">MP4, MOV, AVI, MKV - 🚀 Stream upload qua server (Không còn lỗi CORS!)</p>
                            <input type="file" id="file-upload" name="file" class="hidden" accept=".mp4,.mov,.avi,.mkv,video/*" required>
                            
                            <div id="file-info" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hidden">
                                <p id="file-name" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                <p id="file-size" class="text-xs text-gray-500 mt-1"></p>
                                <button type="submit" id="upload-btn"
                                        class="mt-3 w-full px-4 py-2 bg-gradient-to-r from-green-500 to-blue-600 text-white rounded-md hover:from-green-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    📤 Upload lên Google Drive
                                </button>
                            </div>
                            
                            <div id="upload-progress" class="mt-4 hidden">
                                <div class="bg-gradient-to-r from-blue-50 to-green-50 border border-blue-200 rounded-md p-4">
                                    <div class="flex items-center">
                                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm bg-gradient-to-r from-blue-700 to-green-700 bg-clip-text text-transparent font-semibold">📤 Đang upload lên Google Drive...</span>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Load Stream Upload JavaScript -->
                        <script>
                            // Initialize stream upload when page loads
                            document.addEventListener('DOMContentLoaded', function() {
                                if (typeof window.initStreamingUpload === 'function') {
                                    window.initStreamingUpload();
                                } else {
                                    console.error('Stream upload script not loaded');
                                }
                            });

                            // Show delete modal
                            window.showDeleteModal = function(fileId, fileName) {
                                window.dispatchEvent(new CustomEvent('show-delete-modal', {
                                    detail: { fileId: fileId, fileName: fileName }
                                }));
                            };

                            // Confirm delete (called from modal)
                            window.confirmDelete = function(fileId, fileName) {
                                // Try Livewire first
                                let deleted = false;
                                
                                if (window.Livewire && window.Livewire.all) {
                                    const components = window.Livewire.all();
                                    const component = components.find(c => 
                                        c.fingerprint && c.fingerprint.name === 'file-manager'
                                    );
                                    
                                    if (component) {
                                        component.call('deleteFile', fileId);
                                        deleted = true;
                                    }
                                }
                                
                                // Fallback: POST request
                                if (!deleted) {
                                    fetch('/file/delete', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({ file_id: fileId })
                                    }).then(response => response.json())
                                    .then(data => {
                                        if (data.status === 'success') {
                                            window.dispatchEvent(new CustomEvent('show-success', {
                                                detail: { message: data.message }
                                            }));
                                            setTimeout(() => window.location.reload(), 1000);
                                        } else {
                                            window.dispatchEvent(new CustomEvent('show-error', {
                                                detail: { message: data.message }
                                            }));
                                        }
                                    }).catch(error => {
                                        window.dispatchEvent(new CustomEvent('show-error', {
                                            detail: { message: 'Lỗi khi xóa file' }
                                        }));
                                    });
                                }
                            };
                        </script>
                    </div>
                </div>
            @endif

            <!-- File Grid -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">File Của Tôi</h2>
                <button wire:click="$refresh" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    🔄 Refresh
                </button>
            </div>
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
                                
                                <button onclick="showDeleteModal({{ $userFile->id }}, '{{ addslashes($userFile->original_name) }}')"
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
