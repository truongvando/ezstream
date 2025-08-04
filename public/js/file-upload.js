// Global file upload handler - can be called from anywhere
window.handleFileUpload = null;

// Global flag to prevent multiple initializations
window.fileUploadInitialized = window.fileUploadInitialized || false;

// Define showDetailedError function globally first
function showDetailedError(errorData) {
    // Check if modal already exists and remove it
    const existingModal = document.querySelector('.error-modal-overlay');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal overlay
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'error-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modalOverlay.style.zIndex = '9999';

    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto';

    // Build modal HTML
    let detailsHtml = '';
    if (errorData.details) {
        detailsHtml = '<div class="mt-4"><h4 class="font-medium text-gray-900 dark:text-white mb-2">Chi tiết:</h4><ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">';
        for (const [key, value] of Object.entries(errorData.details)) {
            const label = formatDetailKey(key);
            detailsHtml += `<li><span class="font-medium">${label}:</span> ${value}</li>`;
        }
        detailsHtml += '</ul></div>';
    }

    let solutionsHtml = '';
    if (errorData.solutions && Array.isArray(errorData.solutions)) {
        solutionsHtml = '<div class="mt-4"><h4 class="font-medium text-gray-900 dark:text-white mb-2">Giải pháp:</h4><ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">';
        errorData.solutions.forEach(solution => {
            solutionsHtml += `<li>• ${solution}</li>`;
        });
        solutionsHtml += '</ul></div>';
    }

    modalContent.innerHTML = `
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    ${errorData.error || 'Lỗi Upload'}
                </h3>
                <button onclick="this.closest('.error-modal-overlay').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            ${errorData.reason ? `<p class="text-sm text-gray-600 dark:text-gray-300 mb-4">${errorData.reason}</p>` : ''}

            ${detailsHtml}
            ${solutionsHtml}

            <div class="mt-6 flex justify-end">
                <button onclick="this.closest('.error-modal-overlay').remove()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    Đóng
                </button>
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



// Immediately expose functions (don't wait for DOMContentLoaded)
function exposeGlobalFunctions() {
    // Global function for showing detailed error modal
    window.showDetailedErrorModal = function(errorData) {
        showDetailedError(errorData);
    };


}

// Expose immediately
exposeGlobalFunctions();

/**
 * Get current streaming method from server
 */
async function getStreamingMethod() {
    try {
        const response = await fetch('/api/settings/streaming-method', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin'
        });

        if (response.ok) {
            const data = await response.json();
            return data.streaming_method || 'ffmpeg_copy';
        }
    } catch (error) {
        // Fallback to default
    }

    return 'ffmpeg_copy'; // Default fallback
}

// Preload TUS library function
function ensureTUSLibrary() {
    return new Promise((resolve) => {
        if (typeof tus !== 'undefined') {
            resolve(true);
            return;
        }

        // Check if script already exists
        const existingScript = document.querySelector('script[src*="tus-js-client"]');
        if (existingScript) {
            // Wait for existing script to load
            existingScript.onload = () => resolve(true);
            existingScript.onerror = () => resolve(false);
            return;
        }

        // Load TUS library
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/tus-js-client@3.1.1/dist/tus.min.js';
        script.onload = () => {
            resolve(true);
        };
        script.onerror = () => {
            resolve(false);
        };
        document.head.appendChild(script);
    });
}

// Only initialize once
if (!window.fileUploadInitialized) {
    document.addEventListener('DOMContentLoaded', function() {
        // Preload TUS library
        ensureTUSLibrary().then(() => {
            initializeFileUpload();
            window.fileUploadInitialized = true;
        });
    });

    // Also initialize when Livewire navigates (for SPA-like behavior)
    document.addEventListener('livewire:navigated', function() {
        if (!window.fileUploadInitialized) {
            ensureTUSLibrary().then(() => {
                initializeFileUpload();
                window.fileUploadInitialized = true;
            });
        }
    });
}

function initializeFileUpload() {
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    const uploadProgress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    const uploadStatus = document.getElementById('upload-status');

    if (!fileInput || !uploadForm) {
        return;
    }

    // Check if already initialized to prevent duplicate listeners
    if (fileInput.hasAttribute('data-initialized')) {
        return;
    }

    // Upload form initialized

    // Mark as initialized
    fileInput.setAttribute('data-initialized', 'true');

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

            // Use unified upload endpoint
            const uploadEndpoint = '/api/generate-upload-url';



            // Step 1: Generate upload URL
            const uploadUrlResponse = await fetch(uploadEndpoint, {
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

            // Step 2: Upload based on method
            if (uploadUrlData.upload_method === 'stream_library') {
                // Stream Library upload for SRS
                updateProgress('📤 Đang upload lên Stream Library...', 10);
                await uploadToStreamLibrary(file, uploadUrlData);

                // Stream Library handles confirmation internally
                updateProgress('✅ Upload hoàn tất!', 100);

                // Delay refresh to let user see completion
                setTimeout(() => {
                    if (window.Livewire) {
                        window.Livewire.dispatch('refreshFiles');
                    }
                    resetForm();
                }, 1500);
                return;
            } else {
                // Step 2: Upload based on method

                switch (uploadUrlData.method) {
                    case 'TUS':
                        // TUS Resumable Upload to Stream Library
                        updateProgress('📤 Đang upload lên Stream Library (TUS)...', 10);
                        await uploadWithTUS(file, uploadUrlData);

                        // Confirm upload with server
                        updateProgress('✅ Đang xác nhận upload...', 95);
                        await confirmTUSUpload(uploadUrlData.upload_token, file.size, file.type);
                        break;

                    case 'POST':
                        // Server upload
                        updateProgress('📤 Đang upload lên server...', 10);
                        await uploadToServer(file, uploadUrlData);
                        break;

                    case 'PUT':
                        // Bunny CDN upload
                        updateProgress('📤 Đang upload lên Bunny CDN...', 10);
                        await uploadToBunny(file, uploadUrlData);
                        break;

                    default:
                        throw new Error(`Unsupported upload method: ${uploadUrlData.method}`);
                }

                // For non-TUS and non-POST uploads, confirm upload
                if (uploadUrlData.method !== 'POST' && uploadUrlData.method !== 'TUS') {
                    updateProgress('✅ Đang xác nhận upload...', 95);

                    const confirmResponse = await fetch('/api/confirm-upload', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            upload_token: uploadUrlData.upload_token,
                            size: file.size,
                            content_type: file.type
                        })
                    });

                    if (!confirmResponse.ok) {
                        const errorData = await confirmResponse.json().catch(() => ({}));
                        throw new Error(errorData.error || 'Confirm upload failed');
                    }
                }

                updateProgress('✅ Upload hoàn tất!', 100);

                // Dispatch global event for other components
                window.dispatchEvent(new CustomEvent('fileUploaded', {
                    detail: {
                        fileName: file.name,
                        fileSize: file.size,
                        uploadToken: uploadUrlData.upload_token,
                        storageMode: uploadUrlData.storage_mode
                    }
                }));

                // Delay refresh to let user see completion
                setTimeout(() => {
                    if (window.Livewire) {
                        window.Livewire.dispatch('refreshFiles');
                    }
                    resetForm();
                }, 1500);

                return;
            }

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
        // Check if modal already exists and remove it
        const existingModal = document.querySelector('.error-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'error-modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
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

    async function uploadToServer(file, uploadData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('file', file);

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
                    resolve();
                } else {
                    reject(new Error(`Server upload failed: ${xhr.status} ${xhr.statusText}`));
                }
            });

            // Error handler
            xhr.addEventListener('error', function() {
                reject(new Error('Network error during upload to server'));
            });

            // Upload to server
            xhr.open('POST', uploadData.upload_url);
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            xhr.send(formData);
        });
    }

    /**
     * Upload file using TUS Resumable Upload to Stream Library
     */
    async function uploadWithTUS(file, uploadData) {
        return new Promise(async (resolve, reject) => {

            // Ensure TUS library is loaded
            const tusLoaded = await ensureTUSLibrary();
            if (!tusLoaded) {
                reject(new Error('TUS library không thể tải được. Vui lòng kiểm tra kết nối internet hoặc chọn storage mode khác.'));
                return;
            }

            if (typeof tus === 'undefined') {
                reject(new Error('TUS library không khả dụng. Vui lòng thử lại.'));
                return;
            }



            startTUSUpload();

            function startTUSUpload() {

            const upload = new tus.Upload(file, {
                endpoint: uploadData.upload_url,
                retryDelays: [0, 3000, 5000, 10000, 20000, 60000],
                headers: {
                    'AuthorizationSignature': uploadData.auth_signature,
                    'AuthorizationExpire': uploadData.auth_expire,
                    'VideoId': uploadData.video_id,
                    'LibraryId': uploadData.library_id,
                },
                metadata: {
                    filetype: file.type,
                    title: file.name,
                },
                onError: function(error) {
                    reject(new Error('TUS upload failed: ' + error.message));
                },
                onProgress: function(bytesUploaded, bytesTotal) {
                    const percentComplete = Math.round((bytesUploaded / bytesTotal) * 80) + 10; // 10-90%
                    updateProgress(`📤 Uploading to Stream Library... ${formatFileSize(bytesUploaded)}/${formatFileSize(bytesTotal)}`, percentComplete);
                },
                onSuccess: function() {
                    resolve();
                }
            });

            // Check for previous uploads and resume if possible
            upload.findPreviousUploads().then(function(previousUploads) {
                if (previousUploads.length) {
                    upload.resumeFromPreviousUpload(previousUploads[0]);
                }
                upload.start();
            }).catch(function() {
                upload.start();
            });
            } // End of startTUSUpload function
        });
    }

    /**
     * Confirm TUS upload with server
     */
    async function confirmTUSUpload(uploadToken, fileSize, mimeType) {
        const response = await fetch('/api/confirm-upload', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                upload_token: uploadToken,
                size: fileSize,
                content_type: mimeType
            })
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Upload file to Stream Library (for SRS streaming)
     */
    async function uploadToStreamLibrary(file, uploadData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('file', file);
            formData.append('upload_token', uploadData.upload_token);

            // Progress handler
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 80) + 10; // 10-90%
                    updateProgress(`📤 Uploading to Stream Library... ${formatFileSize(e.loaded)}/${formatFileSize(e.total)}`, percentComplete);
                }
            });

            // Success handler
            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            updateProgress('🎬 Video uploaded to Stream Library successfully!', 90);
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Stream Library upload failed'));
                        }
                    } catch (e) {
                        reject(new Error('Invalid response from Stream Library upload'));
                    }
                } else {
                    reject(new Error(`Stream Library upload failed: ${xhr.status} ${xhr.statusText}`));
                }
            });

            // Error handler
            xhr.addEventListener('error', function() {
                reject(new Error('Network error during Stream Library upload'));
            });

            // Upload to Stream Library
            xhr.open('POST', '/api/stream-library/upload');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            xhr.send(formData);
        });
    }

    function showProgress() {
        uploadProgress.classList.remove('hidden');
        uploadProgress.style.display = 'block'; // Override any inline style
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
        uploadProgress.style.display = 'none'; // Ensure it's hidden
        uploadForm.querySelector('input').disabled = false;
        fileInput.value = '';
        progressBar.style.width = '0%';
        uploadStatus.textContent = 'Đang chuẩn bị...';
        progressBar.className = 'bg-blue-600 h-2 rounded-full transition-all duration-300';

        // Clear global flags
        window.uploadNotificationSent = false;
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
    window.showDetailedError = showDetailedError;



    // Expose functions globally
    window.handleFileUpload = handleFileUpload;
    window.formatDetailKey = formatDetailKey;


}
