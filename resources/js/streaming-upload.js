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
        await handleStreamingProxyUpload();
    });

    async function handleStreamingProxyUpload() {
        try {
            uploadBtn.disabled = true;
            updateProgress("Đang khởi tạo upload stream...", 0);

            // Validate file type
            const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
            if (!allowedTypes.includes(selectedFile.type)) {
                throw new Error('Chỉ hỗ trợ file video (mp4, mov, avi, mkv)');
            }

            // Check file size (max 2GB)
            if (selectedFile.size > 2 * 1024 * 1024 * 1024) {
                throw new Error('File không được vượt quá 2GB');
            }

            // Stream upload với progress tracking
            const xhr = new XMLHttpRequest();
            
            // Track upload progress
            xhr.upload.addEventListener("progress", (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 95); // 0-95%
                    const mb = Math.round(e.loaded / 1024 / 1024);
                    const totalMB = Math.round(e.total / 1024 / 1024);
                    updateProgress(`Đang stream: ${mb}MB / ${totalMB}MB`, percent);
                }
            });

            xhr.addEventListener("load", () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            updateProgress("Upload thành công!", 100);
                            
                            // Auto refresh file list
                            setTimeout(() => {
                                refreshFileList();
                                resetForm();
                            }, 1500);
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    } catch (e) {
                        throw new Error('Invalid server response');
                    }
                } else {
                    throw new Error(`Upload failed: ${xhr.status} ${xhr.statusText}`);
                }
            });

            xhr.addEventListener("error", () => {
                throw new Error("Network error during upload");
            });

            // Set headers with file info (không dùng FormData để tránh multipart)
            xhr.open("POST", "/file/stream-proxy", true);
            xhr.setRequestHeader("X-CSRF-TOKEN", getCsrfToken());
            xhr.setRequestHeader("X-File-Name", selectedFile.name);
            xhr.setRequestHeader("X-File-Size", selectedFile.size.toString());
            xhr.setRequestHeader("X-File-Type", selectedFile.type);
            xhr.setRequestHeader("Content-Type", selectedFile.type);

            updateProgress("Đang stream lên Google Drive...", 5);
            
            // Send file as raw binary stream
            xhr.send(selectedFile);
            
        } catch (error) {
            console.error("Streaming proxy upload error:", error);
            updateProgress("Lỗi: " + error.message, 0);
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