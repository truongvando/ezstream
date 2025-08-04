<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Debug TUS Upload - EzStream</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { padding: 15px; margin: 10px 0; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; max-height: 300px; font-size: 12px; }
        button { padding: 12px 24px; margin: 8px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        input[type="file"] { margin: 10px 0; padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%; }
        .progress { width: 100%; height: 25px; background: #e9ecef; border-radius: 12px; margin: 15px 0; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #007bff, #0056b3); border-radius: 12px; transition: width 0.3s ease; text-align: center; line-height: 25px; color: white; font-weight: 500; }
        .section { margin: 30px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; }
        .section h2 { margin-top: 0; color: #495057; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-info { background: #17a2b8; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug TUS Upload - EzStream</h1>
        <p>Tool này giúp debug và kiểm tra TUS upload cho Bunny Stream Library</p>
        
        <div class="grid">
            <div class="section">
                <h2>1. 📚 Kiểm tra TUS Library</h2>
                <button class="btn-primary" onclick="checkTUSLibrary()">Kiểm tra TUS Library</button>
                <div id="tus-status"></div>
            </div>
            
            <div class="section">
                <h2>2. ⚙️ Kiểm tra Cấu hình</h2>
                <button class="btn-primary" onclick="checkBunnyConfig()">Kiểm tra Cấu hình</button>
                <button class="btn-secondary" onclick="checkStorageMode()">Kiểm tra Storage Mode</button>
                <div id="config-status"></div>
            </div>
        </div>
        
        <div class="section">
            <h2>3. 🔗 Test Upload URL Generation</h2>
            <input type="file" id="test-file" accept="video/*" placeholder="Chọn file video để test...">
            <button class="btn-primary" onclick="testUploadURL()">Test Generate Upload URL</button>
            <div id="url-status"></div>
        </div>
        
        <div class="section">
            <h2>4. 📤 Test TUS Upload</h2>
            <button class="btn-success" onclick="testTUSUpload()" id="tus-upload-btn" disabled>Bắt đầu TUS Upload</button>
            <button class="btn-danger" onclick="cancelUpload()" id="cancel-btn" style="display: none;">Hủy Upload</button>
            <div class="progress" style="display: none;" id="upload-progress">
                <div class="progress-bar" id="progress-bar" style="width: 0%">0%</div>
            </div>
            <div id="upload-status"></div>
        </div>
        
        <div class="section">
            <h2>5. 📋 Debug Logs</h2>
            <button class="btn-secondary" onclick="clearLogs()">Clear Logs</button>
            <button class="btn-secondary" onclick="downloadLogs()">Download Logs</button>
            <pre id="debug-logs">Logs sẽ hiển thị ở đây...</pre>
        </div>
    </div>

    <!-- TUS Library -->
    <script src="https://cdn.jsdelivr.net/npm/tus-js-client@3.1.1/dist/tus.min.js"></script>
    
    <script>
        let uploadData = null;
        let debugLogs = [];
        let currentUpload = null;
        
        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            debugLogs.push(`[${timestamp}] ${type.toUpperCase()}: ${message}`);
            updateDebugLogs();
            console.log(`[TUS Debug] ${message}`);
        }
        
        function updateDebugLogs() {
            document.getElementById('debug-logs').textContent = debugLogs.join('\n');
        }
        
        function clearLogs() {
            debugLogs = [];
            updateDebugLogs();
        }
        
        function downloadLogs() {
            const blob = new Blob([debugLogs.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `tus-debug-logs-${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function showStatus(containerId, message, type) {
            const container = document.getElementById(containerId);
            container.innerHTML = `<div class="status ${type}">${message}</div>`;
        }
        
        function checkTUSLibrary() {
            log('Checking TUS library...');
            
            if (typeof tus === 'undefined') {
                showStatus('tus-status', '❌ TUS library không được load! <span class="badge badge-danger">FAILED</span>', 'error');
                log('TUS library not loaded', 'error');
                return false;
            }
            
            let details = '✅ TUS library đã được load thành công! <span class="badge badge-success">OK</span><br>';
            
            if (tus.Upload) {
                details += '📦 TUS Upload class: <span class="badge badge-success">Available</span><br>';
                log('TUS Upload class available');
            }
            
            if (tus.isSupported) {
                const supported = tus.isSupported;
                details += `🌐 Browser support: <span class="badge badge-${supported ? 'success' : 'danger'}">${supported ? 'Supported' : 'Not Supported'}</span><br>`;
                log(`Browser support: ${supported}`);
            }
            
            details += '🔗 CDN: https://cdn.jsdelivr.net/npm/tus-js-client@3.1.1/dist/tus.min.js';
            
            showStatus('tus-status', details, 'success');
            return true;
        }
        
        async function checkBunnyConfig() {
            log('Checking Bunny Stream configuration...');
            
            try {
                const response = await fetch('/api/settings/streaming-method', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                const data = await response.json();
                
                log(`Streaming method: ${data.streaming_method}`);
                
                showStatus('config-status', 
                    `✅ API Response thành công! <span class="badge badge-success">OK</span><br>` +
                    `📡 Streaming Method: <span class="badge badge-info">${data.streaming_method}</span>`, 
                    'success');
                    
            } catch (error) {
                log(`Config check failed: ${error.message}`, 'error');
                showStatus('config-status', `❌ Lỗi kiểm tra cấu hình: ${error.message} <span class="badge badge-danger">FAILED</span>`, 'error');
            }
        }
        
        async function checkStorageMode() {
            log('Checking current storage mode...');

            try {
                const response = await fetch('/api/settings/storage-mode', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                const data = await response.json();

                log(`Current storage mode: ${data.storage_mode}`);

                const currentStatus = document.getElementById('config-status').innerHTML;
                showStatus('config-status',
                    currentStatus + `<br>💾 Storage Mode: <span class="badge badge-info">${data.storage_mode}</span>`,
                    'success');

            } catch (error) {
                log(`Storage mode check failed: ${error.message}`, 'error');
                showStatus('config-status',
                    `❌ Lỗi kiểm tra storage mode: ${error.message} <span class="badge badge-danger">FAILED</span>`,
                    'error');
            }
        }
        
        async function testUploadURL() {
            const fileInput = document.getElementById('test-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showStatus('url-status', '❌ Vui lòng chọn file để test! <span class="badge badge-danger">NO FILE</span>', 'error');
                return;
            }
            
            log(`Testing upload URL generation for file: ${file.name} (${file.size} bytes, ${file.type})`);
            
            try {
                const response = await fetch('/api/generate-upload-url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        filename: file.name,
                        content_type: file.type,
                        size: file.size
                    })
                });
                
                const data = await response.json();
                log(`Upload URL response: ${JSON.stringify(data, null, 2)}`);
                
                if (data.status === 'success') {
                    uploadData = data;
                    
                    let details = `✅ Upload URL được tạo thành công! <span class="badge badge-success">SUCCESS</span><br>`;
                    details += `📡 Method: <span class="badge badge-info">${data.method}</span><br>`;
                    details += `🏪 Storage Mode: <span class="badge badge-info">${data.storage_mode}</span><br>`;
                    details += `🔗 Upload URL: ${data.upload_url.substring(0, 60)}...<br>`;
                    
                    if (data.method === 'TUS') {
                        details += `🎥 Video ID: <span class="badge badge-info">${data.video_id || 'N/A'}</span><br>`;
                        details += `📚 Library ID: <span class="badge badge-info">${data.library_id || 'N/A'}</span>`;
                    }
                    
                    showStatus('url-status', details, 'success');
                    
                    // Enable TUS upload button if method is TUS
                    if (data.method === 'TUS') {
                        document.getElementById('tus-upload-btn').disabled = false;
                        log('TUS upload button enabled');
                    } else {
                        log(`Upload method is ${data.method}, not TUS`);
                        showStatus('url-status', 
                            details + `<br>⚠️ Method không phải TUS, không thể test TUS upload`, 
                            'warning');
                    }
                } else {
                    showStatus('url-status', `❌ Lỗi tạo upload URL: ${data.error} <span class="badge badge-danger">FAILED</span>`, 'error');
                    log(`Upload URL generation failed: ${data.error}`, 'error');
                }
                
            } catch (error) {
                log(`Upload URL test failed: ${error.message}`, 'error');
                showStatus('url-status', `❌ Lỗi test upload URL: ${error.message} <span class="badge badge-danger">ERROR</span>`, 'error');
            }
        }
        
        async function testTUSUpload() {
            if (!uploadData) {
                showStatus('upload-status', '❌ Vui lòng test generate upload URL trước! <span class="badge badge-danger">NO URL</span>', 'error');
                return;
            }
            
            const fileInput = document.getElementById('test-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showStatus('upload-status', '❌ Vui lòng chọn file để upload! <span class="badge badge-danger">NO FILE</span>', 'error');
                return;
            }
            
            if (uploadData.method !== 'TUS') {
                showStatus('upload-status', `❌ Upload method không phải TUS: ${uploadData.method} <span class="badge badge-danger">WRONG METHOD</span>`, 'error');
                return;
            }
            
            log(`Starting TUS upload for file: ${file.name}`);
            
            // Show progress bar and cancel button
            document.getElementById('upload-progress').style.display = 'block';
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('tus-upload-btn').disabled = true;
            
            try {
                currentUpload = new tus.Upload(file, {
                    endpoint: uploadData.upload_url,
                    retryDelays: [0, 3000, 5000, 10000],
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
                        log(`TUS upload error: ${error.message}`, 'error');
                        showStatus('upload-status', `❌ TUS upload failed: ${error.message} <span class="badge badge-danger">FAILED</span>`, 'error');
                        resetUploadUI();
                    },
                    onProgress: function(bytesUploaded, bytesTotal) {
                        const percent = Math.round((bytesUploaded / bytesTotal) * 100);
                        document.getElementById('progress-bar').style.width = percent + '%';
                        document.getElementById('progress-bar').textContent = percent + '%';
                        log(`Upload progress: ${percent}% (${bytesUploaded}/${bytesTotal} bytes)`);
                    },
                    onSuccess: function() {
                        log('TUS upload completed successfully!');
                        showStatus('upload-status', '✅ TUS upload hoàn thành thành công! <span class="badge badge-success">SUCCESS</span>', 'success');
                        resetUploadUI();
                    }
                });
                
                currentUpload.start();
                log('TUS upload started');
                showStatus('upload-status', '📤 Đang upload với TUS... <span class="badge badge-info">UPLOADING</span>', 'info');
                
            } catch (error) {
                log(`TUS upload initialization failed: ${error.message}`, 'error');
                showStatus('upload-status', `❌ Lỗi khởi tạo TUS upload: ${error.message} <span class="badge badge-danger">INIT FAILED</span>`, 'error');
                resetUploadUI();
            }
        }
        
        function cancelUpload() {
            if (currentUpload) {
                currentUpload.abort();
                log('TUS upload cancelled by user');
                showStatus('upload-status', '⏹️ Upload đã bị hủy <span class="badge badge-info">CANCELLED</span>', 'info');
                resetUploadUI();
            }
        }
        
        function resetUploadUI() {
            document.getElementById('upload-progress').style.display = 'none';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('tus-upload-btn').disabled = false;
            document.getElementById('progress-bar').style.width = '0%';
            document.getElementById('progress-bar').textContent = '0%';
            currentUpload = null;
        }
        
        // Auto-check TUS library on page load
        window.addEventListener('load', function() {
            log('Debug page loaded');
            setTimeout(() => {
                checkTUSLibrary();
                checkBunnyConfig();
            }, 1000);
        });
    </script>
</body>
</html>
<?php /**PATH D:\laragon\www\ezstream\resources\views/debug-tus.blade.php ENDPATH**/ ?>