// Global file upload handler - can be called from anywhere
window.handleFileUpload = null;

// Immediately expose functions (don't wait for DOMContentLoaded)
function exposeGlobalFunctions() {
    // Global function for showing detailed error modal
    window.showDetailedErrorModal = function(errorData) {
        if (typeof showDetailedError === 'function') {
            showDetailedError(errorData);
        } else {
            console.error('showDetailedError function not found');
            alert('Error: ' + (errorData.error || 'Unknown error'));
        }
    };
}

// Expose immediately
exposeGlobalFunctions();

document.addEventListener('DOMContentLoaded', function() {
    initializeFileUpload();
});

// Also initialize when Livewire navigates (for SPA-like behavior)
document.addEventListener('livewire:navigated', function() {
    initializeFileUpload();
});

function initializeFileUpload() {
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    const uploadProgress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    const uploadStatus = document.getElementById('upload-status');

    if (!fileInput || !uploadForm) {
        return;
    }

    console.log('✅ Upload form initialized successfully');

    // File input change handler
    fileInput.addEventListener('change', function(e) {
        console.log('📁 File input changed:', e.target.files);
        const file = e.target.files[0];
        if (file) {
            console.log('📁 Selected file:', file.name, file.size, file.type);
            handleFileUpload(file);
        }
    });

    // Drag and drop handlers
    uploadForm.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadForm.classList.add('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
    });

    uploadForm.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadForm.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
    });

    uploadForm.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadForm.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
        
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
        const maxSize = 20 * 1024 * 1024 * 1024; // 20GB
        if (file.size > maxSize) {
            alert('File quá lớn. Tối đa 20GB.');
            resetForm();
            return;
        }

        console.log('🚀 Starting upload:', file.name, 'Size:', formatFileSize(file.size));
        
        // Call custom start handler if it exists
        if (typeof window.uploadStartHandler === 'function') {
            window.uploadStartHandler();
        }

        // Show progress early
        showProgress();
        updateProgress('🔬 Đang đọc thông tin video...', 2);

        try {
            // Get video dimensions
            const dimensions = await getVideoDimensions(file);

            updateProgress('📋 Đang tạo URL upload...', 5);

            // Step 1: Generate upload URL
            const uploadUrlResponse = await fetch('/api/generate-upload-url', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    filename: file.name,
                    size: file.size,
                    content_type: file.type,
                    width: dimensions.width,
                    height: dimensions.height,
                })
            });

            if (!uploadUrlResponse.ok) {
                const errorData = await uploadUrlResponse.json().catch(() => ({ error: `Lỗi không xác định. Vui lòng thử lại.` }));

                // Handle detailed error response
                if (errorData.reason && errorData.details && errorData.solutions) {
                    showDetailedError(errorData);
                    return;
                }

                throw new Error(errorData.error || `HTTP ${uploadUrlResponse.status}: ${uploadUrlResponse.statusText}`);
            }

            const uploadUrlData = await uploadUrlResponse.json();

            if (uploadUrlData.status !== 'success') {
                throw new Error(uploadUrlData.message || 'Failed to generate upload URL');
            }

            // Step 2: Upload directly to Bunny.net
            updateProgress('📤 Đang upload lên Bunny.net CDN...', 10);

            await uploadToBunny(file, uploadUrlData);

            // Step 3: Confirm upload
            updateProgress('✅ Đang xác nhận upload...', 95);

            const confirmResponse = await fetch('/api/confirm-upload', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    upload_token: uploadUrlData.upload_token,
                    size: file.size,
                    content_type: file.type
                })
            });

            if (!confirmResponse.ok) {
                const errorData = await confirmResponse.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to confirm upload');
            }

            const confirmData = await confirmResponse.json();

            if (confirmData.status !== 'success') {
                throw new Error(confirmData.message || 'Upload confirmation failed');
            }

            // Success!
            updateProgress('🎉 Upload hoàn tất!', 100);
            
            // Notify Livewire component or call custom success handler
            if (typeof window.uploadSuccessHandler === 'function') {
                window.uploadSuccessHandler({
                    file_name: file.name,
                    file_id: confirmData.file.id,
                    file_size: file.size
                });
            } else if (window.Livewire) {
                window.Livewire.dispatch('fileUploaded', {
                    file_name: file.name,
                    file_id: confirmData.file.id, // Sửa lại đường dẫn
                    file_size: file.size
                });
            }

            // Reset form after delay
            setTimeout(() => {
                resetForm();
            }, 2000);

        } catch (error) {
            updateProgress('❌ Lỗi: ' + error.message, 0);
            
            // Call custom error handler
            if (typeof window.uploadErrorHandler === 'function') {
                window.uploadErrorHandler();
            }

            setTimeout(resetForm, 3000);
        }
    }

    function showDetailedError(errorData) {

        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
        modalOverlay.style.zIndex = '9999';

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.className = 'bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto';

        modalContent.innerHTML = `
            <div class="p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upload Không Thành Công</h3>
                    </div>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Error Message -->
                <div class="mb-4">
                    <p class="text-red-600 dark:text-red-400 font-medium mb-2">${errorData.error || 'Có lỗi xảy ra'}</p>
                    ${errorData.reason ? `<p class="text-gray-600 dark:text-gray-400 text-sm">${errorData.reason}</p>` : ''}
                </div>

                <!-- Details -->
                ${errorData.details ? `
                <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Chi tiết:</h4>
                    <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        ${Object.entries(errorData.details).map(([key, value]) => `
                            <div><span class="font-medium">${formatDetailKey(key)}:</span> ${value}</div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <!-- Solutions -->
                ${errorData.solutions ? `
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">💡 Giải pháp:</h4>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        ${errorData.solutions.map(solution => `
                            <li class="flex items-start">
                                <span class="text-blue-500 mr-2">•</span>
                                <span>${solution}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
                ` : ''}

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="this.closest('.fixed').remove()"
                            class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        Đóng
                    </button>
                    ${errorData.details && errorData.details.package_name ? `
                    <button onclick="window.location.href='/services'"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Nâng Cấp Gói
                    </button>
                    ` : ''}
                </div>
            </div>
        `;

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Close on overlay click
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.remove();
            }
        });

        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                modalOverlay.remove();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);

        const progressContainer = document.getElementById('upload-progress');

        progressContainer.innerHTML = `
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-medium text-red-800 dark:text-red-200">
                            ${errorData.error}
                        </h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            <strong>Lý do:</strong> ${errorData.reason}
                        </p>

                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-red-800 dark:text-red-200">Chi tiết:</h4>
                            <ul class="mt-2 text-sm text-red-700 dark:text-red-300 space-y-1">
                                ${Object.entries(errorData.details).map(([key, value]) =>
                                    `<li><strong>${key.replace(/_/g, ' ')}:</strong> ${value}</li>`
                                ).join('')}
                            </ul>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-red-800 dark:text-red-200">Giải pháp:</h4>
                            <ul class="mt-2 text-sm text-red-700 dark:text-red-300 space-y-1">
                                ${errorData.solutions.map(solution => `<li>• ${solution}</li>`).join('')}
                            </ul>
                        </div>

                        <div class="mt-6">
                            <button onclick="resetForm()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Thử lại
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Auto reset after 10 seconds
        if (typeof window.uploadErrorHandler === 'function') {
            window.uploadErrorHandler();
        }
        setTimeout(resetForm, 10000);
    }

    async function uploadToBunny(file, uploadData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Progress handler
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 80) + 10; // 10-90%
                    updateProgress(`📤 Đang upload... ${formatFileSize(e.loaded)}/${formatFileSize(e.total)}`, percentComplete);
                }
            });

            // Success handler
            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    console.log('✅ Upload to Bunny.net successful');
                    resolve();
                } else {
                    reject(new Error(`Bunny.net upload failed: ${xhr.status} ${xhr.statusText}`));
                }
            });

            // Error handler
            xhr.addEventListener('error', function() {
                reject(new Error('Network error during upload to Bunny.net'));
            });

            // Upload to Bunny.net
            xhr.open('PUT', uploadData.upload_url);
            xhr.setRequestHeader('AccessKey', uploadData.access_key);
            xhr.setRequestHeader('Content-Type', file.type);
            xhr.send(file);
        });
    }

    function showProgress() {
        uploadProgress.classList.remove('hidden');
        uploadForm.querySelector('input').disabled = true;
    }

    function updateProgress(message, percent) {
        uploadStatus.textContent = message;
        progressBar.style.width = percent + '%';
        
        // Change color based on progress
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
        uploadForm.querySelector('input').disabled = false;
        fileInput.value = '';
        progressBar.style.width = '0%';
        uploadStatus.textContent = 'Đang chuẩn bị...';
        progressBar.className = 'bg-blue-600 h-2 rounded-full transition-all duration-300';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function getVideoDimensions(file) {
        return new Promise((resolve, reject) => {
            const video = document.createElement('video');
            video.preload = 'metadata';

            video.onloadedmetadata = function() {
                window.URL.revokeObjectURL(video.src);
                resolve({
                    width: video.videoWidth,
                    height: video.videoHeight
                });
            };

            video.onerror = function() {
                reject(new Error('Không thể đọc metadata của file video. File có thể bị lỗi.'));
            };

            video.src = window.URL.createObjectURL(file);
        });
    }

    // Helper function to format detail keys
    function formatDetailKey(key) {
        const keyMap = {
            'video_resolution': 'Độ phân giải video',
            'package_name': 'Gói hiện tại',
            'package_limit': 'Giới hạn gói',
            'supported_orientations': 'Hướng hỗ trợ',
            'storage_used': 'Dung lượng đã dùng',
            'file_size': 'Kích thước file',
            'remaining_space': 'Dung lượng còn lại'
        };
        return keyMap[key] || key;
    }

    // Global function for showing detailed error modal
    window.showDetailedErrorModal = function(errorData) {
        showDetailedError(errorData);
    };

    // Expose functions globally
    window.handleFileUpload = handleFileUpload;
    window.formatDetailKey = formatDetailKey;


}
