// STREAMING PROXY UPLOAD - Tối ưu nhất!
// File stream trực tiếp: Browser -> Server -> Google Drive
// Không lưu file tạm, không double upload, memory usage thấp
window.initStreamingUpload = function() {
    const uploadForm = document.getElementById("upload-form");

    // Guard clause: If the form doesn't exist on this page, do nothing.
    if (!uploadForm) {
        return;
    }
    
    const fileInput = document.getElementById("file-upload");
    const fileInfo = document.getElementById("file-info");
    const uploadProgress = document.getElementById("upload-progress");
    const fileNameElement = document.getElementById("file-name");
    const fileSizeElement = document.getElementById("file-size");
    const uploadBtn = document.getElementById("upload-btn");

    let selectedFile = null;

    fileInput.addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (file) {
            selectedFile = file;
            fileNameElement.textContent = "File: " + file.name;
            fileSizeElement.textContent = "Size: " + formatFileSize(file.size);
            fileInfo.classList.remove("hidden");
        }
    });

    uploadForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        if (!selectedFile) {
            alert("Vui lòng chọn file");
            return;
        }
        await handleStandardUpload();
    });

    async function handleStandardUpload() {
        try {
            uploadBtn.disabled = true;
            updateProgress("Đang chuẩn bị file...", 0);

            // Validate file type
            const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
            if (!allowedTypes.includes(selectedFile.type)) {
                throw new Error('Chỉ hỗ trợ file video (mp4, mov, avi, mkv)');
            }

            // Check file size (max 2GB) - Server side will also check
            if (selectedFile.size > 2 * 1024 * 1024 * 1024) {
                throw new Error('File không được vượt quá 2GB');
            }
            
            const formData = new FormData();
            formData.append('file', selectedFile);

            // Use XMLHttpRequest for progress tracking
            const xhr = new XMLHttpRequest();
            
            // Track upload progress
            xhr.upload.addEventListener("progress", (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 95); // 0-95% for upload, 5% for processing
                    const mb = Math.round(e.loaded / 1024 / 1024);
                    const totalMB = Math.round(e.total / 1024 / 1024);
                    updateProgress(`Đang tải lên server: ${mb}MB / ${totalMB}MB`, percent);
                }
            });

            xhr.addEventListener("load", () => {
                updateProgress("Đang xử lý và đồng bộ hóa...", 98);
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            updateProgress("✅ Upload hoàn tất!", 100);
                            
                            // Auto refresh file list
                            setTimeout(() => {
                                refreshFileList();
                                resetForm();
                            }, 1500);
                        } else {
                            throw new Error(response.message || 'Upload failed on server processing.');
                        }
                    } catch (e) {
                        throw new Error('Invalid server response.');
                    }
                } else {
                    let errorMessage = `Upload failed: ${xhr.status} ${xhr.statusText}`;
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {}
                    throw new Error(errorMessage);
                }
            });

            xhr.addEventListener("error", () => {
                throw new Error("Lỗi mạng trong quá trình upload.");
            });

            // Use a new route for standard upload
            xhr.open("POST", "/file/upload", true); 
            xhr.setRequestHeader("X-CSRF-TOKEN", getCsrfToken());
            
            xhr.send(formData);
            
        } catch (error) {
            console.error("Standard upload error:", error);
            updateProgress("❌ Lỗi: " + error.message, 0);
            uploadBtn.disabled = false;
        }
    }

    function refreshFileList() {
        let refreshed = false;
        
        // Method 1: Livewire dispatch
        if (window.Livewire) {
            try {
                window.Livewire.dispatch('refreshFiles');
                refreshed = true;
            } catch (e) {
                console.log('Livewire dispatch failed, trying component refresh');
            }
            
            // Method 2: Component refresh
            if (!refreshed && window.Livewire.all) {
                window.Livewire.all().forEach(component => {
                    if (component.fingerprint && component.fingerprint.name === 'file-manager') {
                        component.call('$refresh');
                        refreshed = true;
                    }
                });
            }
        }
        
        // Method 3: Page reload as fallback
        if (!refreshed) {
            console.log('Refreshing page as fallback');
            window.location.reload();
        }
    }

    function updateProgress(message, percentage) {
        const span = uploadProgress.querySelector("span");
        if (span) {
            span.textContent = message;
        }
        
        let progressBar = uploadProgress.querySelector(".progress-bar");
        if (!progressBar) {
            progressBar = document.createElement("div");
            progressBar.className = "progress-bar w-full bg-gray-200 rounded-full h-3 mt-3";
            progressBar.innerHTML = '<div class="bg-gradient-to-r from-blue-500 to-green-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>';
            uploadProgress.appendChild(progressBar);
        }
        
        const progressFill = progressBar.querySelector("div");
        if (progressFill) {
            progressFill.style.width = percentage + "%";
            
            // Color changes based on progress
            if (percentage >= 95) {
                progressFill.className = "bg-green-500 h-3 rounded-full transition-all duration-300";
            } else if (percentage >= 50) {
                progressFill.className = "bg-blue-500 h-3 rounded-full transition-all duration-300";
            }
        }
        uploadProgress.classList.remove("hidden");
    }

    function resetForm() {
        selectedFile = null;
        fileInput.value = "";
        fileInfo.classList.add("hidden");
        uploadProgress.classList.add("hidden");
        uploadBtn.disabled = false;
        
        const progressBar = uploadProgress.querySelector(".progress-bar");
        if (progressBar) {
            progressBar.remove();
        }
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    }

    function formatFileSize(bytes) {
        const k = 1024;
        const sizes = ["Bytes", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", window.initStreamingUpload);
} else {
    window.initStreamingUpload();
} 