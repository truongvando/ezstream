document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 File upload script loaded');

    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    const uploadProgress = document.getElementById('upload-progress');
    const progressBar = document.getElementById('progress-bar');
    const uploadStatus = document.getElementById('upload-status');

    console.log('Elements found:', {
        fileInput: !!fileInput,
        uploadForm: !!uploadForm,
        uploadProgress: !!uploadProgress,
        progressBar: !!progressBar,
        uploadStatus: !!uploadStatus
    });

    if (!fileInput || !uploadForm) {
        console.error('❌ Upload form elements not found on this page');
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

        try {
            // Show progress
            showProgress();
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
                    file_name: file.name,
                    file_size: file.size,
                    mime_type: file.type
                })
            });

            if (!uploadUrlResponse.ok) {
                const errorData = await uploadUrlResponse.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${uploadUrlResponse.status}: ${uploadUrlResponse.statusText}`);
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
                    file_size: file.size,
                    mime_type: file.type
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
            
            // Notify Livewire component
            if (window.Livewire) {
                window.Livewire.dispatch('fileUploaded', {
                    file_name: file.name,
                    file_id: confirmData.file_id,
                    file_size: file.size
                });
            }

            // Reset form after delay
            setTimeout(() => {
                resetForm();
            }, 2000);

        } catch (error) {
            console.error('Upload failed:', error);
            updateProgress('❌ Lỗi: ' + error.message, 0);
            setTimeout(resetForm, 3000);
        }
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
});
