document.addEventListener('DOMContentLoaded', () => {
    const uploadForm = document.getElementById('upload-form');
    if (!uploadForm) return;

    const fileInput = document.getElementById('file-input');
    const uploadPrompt = document.getElementById('upload-prompt');
    const progressContainer = document.getElementById('upload-progress-container');
    const progressBar = document.querySelector('#upload-progress-bar');
    const progressText = document.querySelector('#upload-progress-text');
    const statusText = document.querySelector('#upload-status-text');
    const cancelBtn = document.getElementById('cancel-upload-btn');

    const UPLOAD_URL = uploadForm.dataset.uploadUrl;
    const MAX_WIDTH = parseInt(uploadForm.dataset.maxWidth, 10);
    const MAX_HEIGHT = parseInt(uploadForm.dataset.maxHeight, 10);
    
    let currentXhr = null;

    // Tab switching functionality
    const directUploadTab = document.getElementById('direct-upload-tab');
    const gdriveImportTab = document.getElementById('gdrive-import-tab');
    const directUploadSection = document.getElementById('direct-upload-section');
    const gdriveImportSection = document.getElementById('gdrive-import-section');

    if (directUploadTab && gdriveImportTab) {
        directUploadTab.addEventListener('click', () => {
            // Switch to direct upload
            directUploadTab.className = 'px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white';
            gdriveImportTab.className = 'px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600';
            
            directUploadSection.classList.remove('hidden');
            gdriveImportSection.classList.add('hidden');
        });

        gdriveImportTab.addEventListener('click', () => {
            // Switch to Google Drive import
            gdriveImportTab.className = 'px-4 py-2 text-sm font-medium rounded-md bg-green-600 text-white';
            directUploadTab.className = 'px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600';
            
            directUploadSection.classList.add('hidden');
            gdriveImportSection.classList.remove('hidden');
        });
    }

    // Google Drive functionality
    const gdriveUrlInput = document.getElementById('gdrive-url');
    const validateGdriveBtn = document.getElementById('validate-gdrive-btn');
    const importGdriveBtn = document.getElementById('import-gdrive-btn');
    const gdriveValidationResult = document.getElementById('gdrive-validation-result');
    const gdriveImportProgress = document.getElementById('gdrive-import-progress');

    if (validateGdriveBtn) {
        validateGdriveBtn.addEventListener('click', async () => {
            const url = gdriveUrlInput.value.trim();
            if (!url) {
                showValidationResult('error', 'Vui lòng nhập link Google Drive');
                return;
            }

            validateGdriveBtn.disabled = true;
            validateGdriveBtn.textContent = '🔍 Đang kiểm tra...';

            try {
                const response = await fetch('/google-drive/validate-url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ url: url })
                });

                const result = await response.json();

                if (result.valid) {
                    showValidationResult('success', 
                        `✅ File hợp lệ: <strong>${result.fileName}</strong><br>` +
                        `📦 Dung lượng: <strong>${formatFileSize(result.fileSize)}</strong>`
                    );
                    importGdriveBtn.disabled = false;
                } else {
                    showValidationResult('error', `❌ ${result.error}`);
                    importGdriveBtn.disabled = true;
                }
            } catch (error) {
                showValidationResult('error', '❌ Lỗi kết nối. Vui lòng thử lại.');
                importGdriveBtn.disabled = true;
            }

            validateGdriveBtn.disabled = false;
            validateGdriveBtn.textContent = '🔍 Kiểm tra Link';
        });
    }

    if (importGdriveBtn) {
        importGdriveBtn.addEventListener('click', async () => {
            const url = gdriveUrlInput.value.trim();
            if (!url) return;

            importGdriveBtn.disabled = true;
            gdriveImportProgress.classList.remove('hidden');

            try {
                const response = await fetch('/google-drive/init-download', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ driveUrl: url })
                });

                const result = await response.json();

                if (response.ok) {
                    showValidationResult('success', 
                        `🎉 Import thành công!<br>` +
                        `File <strong>${result.fileName}</strong> đang được tải về.<br>` +
                        `Bạn sẽ thấy file trong danh sách khi hoàn tất.`
                    );
                    
                    // Reset form
                    gdriveUrlInput.value = '';
                    importGdriveBtn.disabled = true;
                    
                    // Refresh file list after 2 seconds
                    setTimeout(() => {
                        if (window.Livewire) {
                            window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('$refresh');
                        }
                    }, 2000);
                } else {
                    showValidationResult('error', `❌ ${result.error || 'Lỗi import file'}`);
                }
            } catch (error) {
                showValidationResult('error', '❌ Lỗi kết nối. Vui lòng thử lại.');
            }

            importGdriveBtn.disabled = false;
            gdriveImportProgress.classList.add('hidden');
        });
    }

    function showValidationResult(type, message) {
        gdriveValidationResult.className = `mt-4 p-4 rounded-lg ${
            type === 'success' 
                ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' 
                : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'
        }`;
        gdriveValidationResult.innerHTML = message;
        gdriveValidationResult.classList.remove('hidden');
    }

    fileInput.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (file) {
            validateAndStartUpload(file);
        }
    });
    
    // Drag and Drop
    uploadForm.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadForm.classList.add('border-indigo-600', 'bg-indigo-50', 'dark:bg-gray-700');
    });
    uploadForm.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadForm.classList.remove('border-indigo-600', 'bg-indigo-50', 'dark:bg-gray-700');
    });
    uploadForm.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadForm.classList.remove('border-indigo-600', 'bg-indigo-50', 'dark:bg-gray-700');
        const file = e.dataTransfer.files[0];
        if (file) {
            fileInput.files = e.dataTransfer.files;
            validateAndStartUpload(file);
        }
    });

    function validateAndStartUpload(file) {
        // Check if file is a video
        if (!file.type.startsWith('video/')) {
            alert('Vui lòng chỉ chọn file video.');
            resetForm();
            return;
        }

        // Check resolution
        getVideoDimensions(file).then(({ width, height }) => {
            console.log(`Video dimensions: ${width}x${height}`);
            console.log(`Max allowed: ${MAX_WIDTH}x${MAX_HEIGHT}`);

            if (width > MAX_WIDTH || height > MAX_HEIGHT) {
                const userFacingResolution = getResolutionName(width, height);
                const packageResolution = getResolutionName(MAX_WIDTH, MAX_HEIGHT);
                alert(`Lỗi: Chất lượng video (${userFacingResolution} - ${width}x${height}) vượt quá giới hạn của gói (${packageResolution} - ${MAX_WIDTH}x${MAX_HEIGHT}).\nVui lòng chọn video có chất lượng phù hợp hoặc nâng cấp gói dịch vụ.`);
                resetForm();
            } else {
                startUpload(file);
            }
        }).catch(error => {
            console.error("Could not get video dimensions:", error);
            alert("Không thể đọc thông tin video. Vui lòng thử lại với file khác.");
            resetForm();
        });
    }

    function getVideoDimensions(file) {
        return new Promise((resolve, reject) => {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = function () {
                window.URL.revokeObjectURL(video.src);
                resolve({ width: video.videoWidth, height: video.videoHeight });
            };
            video.onerror = function () {
                reject("Error loading video metadata.");
            };
            video.src = window.URL.createObjectURL(file);
        });
    }
    
    function getResolutionName(width, height) {
        if (width >= 3840 || height >= 2160) return "4K UHD";
        if (width >= 1920 || height >= 1080) return "Full HD (1080p)";
        if (width >= 1280 || height >= 720) return "HD (720p)";
        return "SD";
    }

    function startUpload(file) {
        // ... (phần code upload giữ nguyên)
// ... existing code ...
        xhr.open('POST', UPLOAD_URL, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        xhr.setRequestHeader('X-File-Name', file.name);
// ... existing code ...
        resetForm();
    }

    function resetForm() {
        fileInput.value = ''; // Important to allow re-selecting the same file
        uploadPrompt.classList.remove('hidden');
        progressContainer.classList.add('hidden');
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        statusText.textContent = '';
        currentXhr = null;
    }
}); 