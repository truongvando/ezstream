<!--
    File này được tái cấu trúc hoàn toàn để sử dụng duy nhất Alpine.js quản lý trạng thái,
    giải quyết triệt để xung đột với Livewire và thống nhất logic cho cả User và Admin.
-->
<div 
    x-data="quickUploader()"
    x-init="
        $watch('selectedFileDetails', (value) => {
            // Khi file được chọn (sau khi upload), gán ID của nó vào thuộc tính của Livewire
            // và tính toán kích thước file một lần duy nhất.
            if (value) {
                $wire.set('video_source_id', value.id, false);
                this.formattedFileSize = this.formatFileSize(value.size);
            } else {
                $wire.set('video_source_id', null, false);
                this.formattedFileSize = '';
            }
        });
    "
    class="space-y-4"
>
    <!-- Giao diện hiển thị khi CHƯA có file nào được chọn -->
    <div x-show="!selectedFileDetails">
        <!-- Vùng Upload -->
        <div 
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="isDragging = false; handleDrop($event)"
            @click="$refs.fileInput.click()"
            class="relative border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer"
            :class="{
                'border-blue-400 bg-blue-50 dark:bg-gray-700': isDragging,
                'border-gray-300 dark:border-gray-600 hover:border-blue-400': !isDragging,
                'opacity-50 pointer-events-none': isUploading
            }"
        >
            <!-- Input file ẩn -->
            <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" class="hidden" accept="video/*" :disabled="isUploading">

            <!-- Nội dung hiển thị -->
            <div class="flex flex-col items-center justify-center space-y-4">
                 <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                 </svg>
                <div>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        <span x-show="!isUploading">Chọn file video hoặc kéo thả vào đây</span>
                        <span x-show="isUploading" x-cloak>Đang xử lý, vui lòng chờ...</span>
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Hỗ trợ: MP4 - H.264 - AAC AUDIO</p>
                </div>
            </div>
        </div>

        <!-- Thanh tiến trình -->
        <div x-show="isUploading" x-cloak class="w-full mt-4">
            <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div 
                    class="h-2.5 rounded-full transition-all duration-300"
                    :class="{
                        'bg-green-600': uploadProgress === 100,
                        'bg-red-600': uploadError,
                        'bg-blue-600': uploadProgress > 0 && uploadProgress < 100 && !uploadError
                    }"
                    :style="`width: ${uploadProgress}%`"
                ></div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 text-center" x-text="uploadStatus"></p>
        </div>
    </div>

    <!-- Giao diện hiển thị SAU KHI upload thành công và file đã được chọn -->
    <div x-show="selectedFileDetails" x-cloak class="bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
                <!-- Icon video -->
                <svg class="h-10 w-10 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm14.553 1.106a.5.5 0 00-.82-.39l-2.253 1.502A.5.5 0 0013 8.5v3a.5.5 0 00.48.494l2.253 1.502a.5.5 0 00.82-.39V7.106zM3 8.5a.5.5 0 00-.5.5v2a.5.5 0 00.5.5h6a.5.5 0 00.5-.5v-2a.5.5 0 00-.5-.5H3z" />
                </svg>
            </div>
            <div class="flex-grow min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="selectedFileDetails ? selectedFileDetails.name : ''"></p>
                <p class="text-sm text-gray-500" x-text="formattedFileSize"></p>
            </div>
            <div class="flex-shrink-0">
                <button @click.prevent="reset()" type="button" class="inline-flex items-center p-1.5 border border-transparent rounded-full shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <!-- Icon xóa -->
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
    function quickUploader() {
        return {
            isUploading: false,
            isDragging: false,
            uploadProgress: 0,
            uploadStatus: '',
            uploadError: false,
            selectedFileDetails: null, // Lưu thông tin file đã upload (id, name, size)
            formattedFileSize: '', // BIẾN MỚI để lưu kích thước file đã định dạng

            handleFileSelect(event) {
                if (event.target.files.length) {
                    this.startUpload(event.target.files[0]);
                }
            },
            handleDrop(event) {
                if (event.dataTransfer.files.length) {
                    this.$refs.fileInput.files = event.dataTransfer.files;
                    this.startUpload(event.dataTransfer.files[0]);
                }
            },
            async startUpload(file) {
                if (!file || !this.validateFile(file)) return;

                this.isUploading = true;
                this.uploadError = false;
                this.uploadProgress = 0;
                
                try {
                    this.updateStatus('🔬 Đang đọc thông tin video...', 2);
                    const dimensions = await this.getVideoDimensions(file);

                    this.updateStatus('📋 Đang tạo URL upload...', 5);
                    const uploadUrlResponse = await fetch('/api/generate-upload-url', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json'},
                        body: JSON.stringify({filename: file.name, size: file.size, content_type: file.type, width: dimensions.width, height: dimensions.height})
                    });
                    if (!uploadUrlResponse.ok) throw new Error((await uploadUrlResponse.json()).message || 'Không thể tạo URL upload.');
                    const uploadUrlData = await uploadUrlResponse.json();

                    this.updateStatus('📤 Đang upload...', 10);
                    await this.uploadToBunny(file, uploadUrlData);
                    
                    this.updateStatus('✅ Đang xác nhận...', 95);
                    const confirmResponse = await fetch('/api/confirm-upload', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', 'Accept': 'application/json'},
                        body: JSON.stringify({upload_token: uploadUrlData.upload_token, size: file.size, content_type: file.type})
                    });
                    if (!confirmResponse.ok) throw new Error((await confirmResponse.json()).message || 'Không thể xác nhận upload.');
                    const confirmData = await confirmResponse.json();

                    this.updateStatus('🎉 Upload hoàn tất!', 100);
                    
                    // Lưu thông tin file vào state của Alpine
                    this.selectedFileDetails = {
                        id: confirmData.file.id,
                        name: confirmData.file.name, // Sửa từ file_name thành name
                        size: confirmData.file.size
                    };

                    setTimeout(() => {
                        this.isUploading = false; // Chỉ tắt trạng thái upload, không reset toàn bộ
                    }, 1000);

                } catch (error) {
                    this.uploadError = true;
                    this.updateStatus(`❌ Lỗi: ${error.message}`, this.uploadProgress);
                    setTimeout(() => this.reset(), 4000); // Reset hoàn toàn nếu có lỗi
                }
            },
            uploadToBunny(file, uploadData) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', e => {
                        if (e.lengthComputable) {
                            const percent = 10 + Math.round((e.loaded / e.total) * 85);
                            this.updateStatus(`📤 Đang upload... ${this.formatFileSize(e.loaded)}/${this.formatFileSize(e.total)}`, percent);
                        }
                    });
                    xhr.addEventListener('load', () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error(`Lỗi upload: ${xhr.statusText}`)));
                    xhr.addEventListener('error', () => reject(new Error('Lỗi mạng.')));
                    xhr.open('PUT', uploadData.upload_url);
                    xhr.setRequestHeader('AccessKey', uploadData.access_key);
                    xhr.setRequestHeader('Content-Type', file.type);
                    xhr.send(file);
                });
            },
            validateFile(file) {
                if (!file.type.startsWith('video/')) {
                    alert('Vui lòng chỉ chọn file video.'); return false;
                }
                if (file.size > 20 * 1024 * 1024 * 1024) { // 20GB
                    alert('File quá lớn. Tối đa 20GB.'); return false;
                }
                return true;
            },
            getVideoDimensions(file) {
                return new Promise((resolve, reject) => {
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.onloadedmetadata = () => { window.URL.revokeObjectURL(video.src); resolve({ width: video.videoWidth, height: video.videoHeight }); };
                    video.onerror = () => reject(new Error('Không thể đọc metadata của file video.'));
                    video.src = URL.createObjectURL(file);
                });
            },
            updateStatus(message, percent) {
                this.uploadStatus = message;
                this.uploadProgress = percent;
            },
            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const i = Math.floor(Math.log(bytes) / Math.log(1024));
                return `${parseFloat((bytes / Math.pow(1024, i)).toFixed(2))} ${['Bytes', 'KB', 'MB', 'GB', 'TB'][i]}`;
            },
            reset() {
                this.isUploading = false;
                this.uploadError = false;
                this.uploadProgress = 0;
                this.uploadStatus = '';
                this.selectedFileDetails = null;
                this.formattedFileSize = ''; // Reset cả biến mới
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            }
        }
    }
</script>
<?php $__env->stopPush(); ?><?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/shared/quick-upload-area.blade.php ENDPATH**/ ?>