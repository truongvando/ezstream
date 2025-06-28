<div>
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
                <x-danger-button wire:click="deleteFile('{{ $deletingFileId }}')" class="w-full sm:w-auto sm:ml-3">
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
                                $usagePercent = ($storageLimit > 0) ? ($storageUsage / $storageLimit) * 100 : 0;
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
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📤 Tải Video Lên</h2>
                        
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
                            <p class="mt-1 text-xs text-gray-500">MP4, MOV, AVI, MKV - Tối đa 2GB</p>
                            <input type="file" id="file-upload" name="file" class="hidden" accept=".mp4,.mov,.avi,.mkv,video/*" required>
                            
                            <div id="file-info" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hidden">
                                <p id="file-name" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                <p id="file-size" class="text-xs text-gray-500 mt-1"></p>
                                <button type="submit" id="upload-btn"
                                        class="mt-3 w-full px-4 py-2 bg-gradient-to-r from-green-500 to-blue-600 text-white rounded-md hover:from-green-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    📤 Bắt đầu Upload
                                </button>
                            </div>
                            
                            <div id="upload-progress" class="mt-4 hidden">
                                <span class="text-sm font-semibold"></span>
                            </div>
                        </form>
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
                            
                            <div class="absolute top-2 right-2 bg-gray-500 text-white text-xs px-2 py-1 rounded">
                                ☁️ Cloud Storage
                            </div>
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
                                
                                <button wire:click="confirmDelete({{ $userFile->id }})"
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
