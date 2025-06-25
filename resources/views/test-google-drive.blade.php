<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Google Drive API - VPS Live Server Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fab fa-google-drive text-blue-500 mr-3"></i>
                Test Google Drive API
            </h1>
            <p class="text-gray-600">Kiểm tra tích hợp Google Drive API cho VPS Live Server Control</p>
        </div>

        <!-- Status Panel -->
        <div id="status-panel" class="bg-white rounded-lg shadow-lg p-6 mb-8 hidden">
            <div class="flex items-center">
                <div id="status-icon" class="mr-3"></div>
                <div>
                    <h3 id="status-title" class="font-semibold"></h3>
                    <p id="status-message" class="text-sm text-gray-600"></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Connection Test -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-plug text-green-500 mr-2"></i>
                    Kiểm Tra Kết Nối
                </h2>
                <p class="text-gray-600 mb-4">Test kết nối với Google Drive API</p>
                <button id="test-connection" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-check-circle mr-2"></i>
                    Test Connection
                </button>
                <div id="connection-result" class="mt-4"></div>
            </div>

            <!-- Upload Test -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-upload text-blue-500 mr-2"></i>
                    Test Upload
                </h2>
                <p class="text-gray-600 mb-4">Upload file test lên Google Drive</p>
                <button id="upload-test" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-file-upload mr-2"></i>
                    Upload Test File
                </button>
                <div id="upload-result" class="mt-4"></div>
            </div>

            <!-- File Upload -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-file-upload text-purple-500 mr-2"></i>
                    Upload File
                </h2>
                <p class="text-gray-600 mb-4">Upload file thực tế lên Google Drive</p>
                <form id="file-upload-form" enctype="multipart/form-data">
                    <input type="file" id="file-input" name="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 mb-4">
                    <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-upload mr-2"></i>
                        Upload File
                    </button>
                </form>
                <div id="file-upload-result" class="mt-4"></div>
            </div>

            <!-- List Files -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-list text-orange-500 mr-2"></i>
                    Danh Sách Files
                </h2>
                <p class="text-gray-600 mb-4">Xem danh sách files trên Google Drive</p>
                <button id="list-files" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-folder-open mr-2"></i>
                    List Files
                </button>
                <div id="files-result" class="mt-4"></div>
            </div>
        </div>

        <!-- Advanced Tests -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-2xl font-semibold mb-6">
                <i class="fas fa-cogs text-indigo-500 mr-2"></i>
                Advanced Tests
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <button id="test-small-upload" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-file-alt mr-2"></i>
                    Test Small Upload
                </button>
                
                <button id="test-large-upload" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-file-archive mr-2"></i>
                    Test Large Upload (10MB)
                </button>
                
                <button id="test-cost" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-calculator mr-2"></i>
                    Cost Analysis
                </button>
            </div>
            
            <div id="advanced-results" class="mt-6"></div>
        </div>

        <!-- Streaming Test -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-2xl font-semibold mb-6">
                <i class="fas fa-video text-red-500 mr-2"></i>
                Streaming Tests
            </h2>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Google Drive File ID:</label>
                <input type="text" id="file-id-input" placeholder="Nhập File ID từ Google Drive" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button id="test-streaming" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-play mr-2"></i>
                    Test Direct Streaming
                </button>
                
                <button id="test-real-streaming" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-broadcast-tower mr-2"></i>
                    Generate FFmpeg Command
                </button>
                
                <button id="benchmark" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Performance Benchmark
                </button>
            </div>
            
            <!-- New Optimized Streaming Tests -->
            <div class="bg-gradient-to-r from-green-50 to-blue-50 p-6 rounded-lg border border-green-200 mt-6">
                <h3 class="text-lg font-semibold text-green-800 mb-4">🚀 Optimized Anti-Lag Streaming</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button id="test-optimized-streaming" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-rocket mr-2"></i>
                        Test Optimized Streaming
                    </button>
                    <button id="test-streaming-health" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-heartbeat mr-2"></i>
                        Streaming Health Monitor
                    </button>
                    <button id="test-batch-performance" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-chart-line mr-2"></i>
                        Batch Performance Test
                    </button>
                </div>
                <div class="mt-4 text-sm text-green-700">
                    <p>✅ Multiple fallback strategies | ✅ Real-time health monitoring | ✅ Anti-lag optimizations</p>
                </div>
            </div>
            
            <!-- Local FFmpeg Testing -->
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-6 rounded-lg border border-purple-200 mt-6">
                <h3 class="text-lg font-semibold text-purple-800 mb-4">🎬 Local FFmpeg Testing</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">RTMP URL (Optional):</label>
                        <input type="text" id="test-rtmp-url" placeholder="rtmp://localhost/live/test" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Preset:</label>
                        <select id="test-preset" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="direct">Direct (Copy)</option>
                            <option value="optimized" selected>Optimized</option>
                            <option value="low_latency">Low Latency</option>
                            <option value="youtube">YouTube</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <button id="test-local-ffmpeg" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-terminal mr-2"></i>
                        Test FFmpeg Installation
                    </button>
                    <button id="test-local-stream" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-play-circle mr-2"></i>
                        Test Local Streaming
                    </button>
                </div>
                <div class="mt-4 text-sm text-purple-700">
                    <p>💡 Test FFmpeg commands locally before deploying to VPS</p>
                </div>
            </div>
            
            <div id="streaming-results" class="mt-6"></div>
        </div>

        <!-- Results Display -->
        <div id="results-container" class="mt-8"></div>
    </div>

    <script>
        // CSRF Token setup
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Utility functions
        function showStatus(type, title, message) {
            const panel = $('#status-panel');
            const icon = $('#status-icon');
            const titleEl = $('#status-title');
            const messageEl = $('#status-message');
            
            panel.removeClass('hidden');
            
            if (type === 'success') {
                icon.html('<i class="fas fa-check-circle text-green-500 text-xl"></i>');
                titleEl.removeClass('text-red-600').addClass('text-green-600');
            } else if (type === 'error') {
                icon.html('<i class="fas fa-exclamation-circle text-red-500 text-xl"></i>');
                titleEl.removeClass('text-green-600').addClass('text-red-600');
            } else {
                icon.html('<i class="fas fa-info-circle text-blue-500 text-xl"></i>');
                titleEl.removeClass('text-green-600 text-red-600').addClass('text-blue-600');
            }
            
            titleEl.text(title);
            messageEl.text(message);
        }

        function displayResult(containerId, data) {
            const container = $(containerId);
            const isSuccess = data.status === 'success';
            
            const html = `
                <div class="border-l-4 ${isSuccess ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'} p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas ${isSuccess ? 'fa-check-circle text-green-400' : 'fa-exclamation-triangle text-red-400'}"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium ${isSuccess ? 'text-green-800' : 'text-red-800'}">
                                ${data.message}
                            </p>
                            ${data.data ? `<pre class="mt-2 text-xs text-gray-600 bg-white p-2 rounded overflow-auto">${JSON.stringify(data.data, null, 2)}</pre>` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            container.html(html);
        }

        // Event handlers
        $('#test-connection').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Testing...');
            
            $.post('/test-google-drive/test-connection')
                .done(function(data) {
                    showStatus(data.status, 'Connection Test', data.message);
                    displayResult('#connection-result', data);
                })
                .fail(function(xhr) {
                    const error = xhr.responseJSON || {message: 'Connection failed'};
                    showStatus('error', 'Connection Failed', error.message);
                    displayResult('#connection-result', {status: 'error', message: error.message});
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-2"></i>Test Connection');
                });
        });

        $('#upload-test').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...');
            
            $.post('/test-google-drive/upload-test')
                .done(function(data) {
                    showStatus(data.status, 'Upload Test', data.message);
                    displayResult('#upload-result', data);
                })
                .fail(function(xhr) {
                    const error = xhr.responseJSON || {message: 'Upload failed'};
                    showStatus('error', 'Upload Failed', error.message);
                    displayResult('#upload-result', {status: 'error', message: error.message});
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-file-upload mr-2"></i>Upload Test File');
                });
        });

        $('#file-upload-form').submit(function(e) {
            e.preventDefault();
            
            const fileInput = $('#file-input')[0];
            if (!fileInput.files.length) {
                showStatus('error', 'No File Selected', 'Please select a file to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            
            $.ajax({
                url: '/test-google-drive/upload-file',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    showStatus('info', 'Uploading...', 'Please wait while file is being uploaded');
                },
                success: function(data) {
                    showStatus(data.status, 'File Upload', data.message);
                    displayResult('#file-upload-result', data);
                    if (data.status === 'success') {
                        $('#file-input').val('');
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON || {message: 'Upload failed'};
                    showStatus('error', 'Upload Failed', error.message);
                    displayResult('#file-upload-result', {status: 'error', message: error.message});
                }
            });
        });

        $('#list-files').click(function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Loading...');
            
            $.get('/test-google-drive/list-files')
                .done(function(data) {
                    showStatus(data.status, 'Files List', data.message);
                    
                    if (data.status === 'success' && data.data.files) {
                        let filesHtml = '<div class="mt-4"><h4 class="font-semibold mb-2">Files trong Google Drive:</h4>';
                        
                        if (data.data.files.length === 0) {
                            filesHtml += '<p class="text-gray-500">Không có file nào</p>';
                        } else {
                            filesHtml += '<div class="space-y-2">';
                            data.data.files.forEach(file => {
                                filesHtml += `
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                        <div>
                                            <p class="font-medium">${file.name}</p>
                                            <p class="text-sm text-gray-500">Size: ${file.size || 'Unknown'} bytes | ID: ${file.id}</p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button onclick="downloadFile('${file.id}')" class="text-blue-500 hover:text-blue-700">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button onclick="deleteFile('${file.id}')" class="text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            filesHtml += '</div>';
                        }
                        filesHtml += '</div>';
                        
                        $('#files-result').html(filesHtml);
                    } else {
                        displayResult('#files-result', data);
                    }
                })
                .fail(function(xhr) {
                    const error = xhr.responseJSON || {message: 'Failed to list files'};
                    showStatus('error', 'List Failed', error.message);
                    displayResult('#files-result', {status: 'error', message: error.message});
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-folder-open mr-2"></i>List Files');
                });
        });

        // Advanced test handlers
        $('#test-small-upload').click(function() {
            runAdvancedTest($(this), '/test-google-drive/test-upload-small', 'Small Upload Test');
        });

        $('#test-large-upload').click(function() {
            runAdvancedTest($(this), '/test-google-drive/test-upload-large', 'Large Upload Test (10MB)');
        });

        $('#test-cost').click(function() {
            runAdvancedTest($(this), '/test-google-drive/test-cost', 'Cost Analysis', 'GET');
        });

        // Streaming test handlers
        $('#test-streaming').click(function() {
            const fileId = $('#file-id-input').val().trim();
            if (!fileId) {
                showStatus('error', 'Missing File ID', 'Please enter a Google Drive File ID');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-streaming', {file_id: fileId}, 'Direct Streaming Test');
        });

        $('#test-real-streaming').click(function() {
            const fileId = $('#file-id-input').val().trim();
            if (!fileId) {
                showStatus('error', 'Missing File ID', 'Please enter a Google Drive File ID');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-real-streaming', {
                file_id: fileId,
                rtmp_url: 'rtmp://your-streaming-server.com/live',
                stream_key: 'your_stream_key'
            }, 'Real Streaming Command');
        });

        $('#benchmark').click(function() {
            const fileId = $('#file-id-input').val().trim();
            if (!fileId) {
                showStatus('error', 'Missing File ID', 'Please enter a Google Drive File ID');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/benchmark', {file_id: fileId}, 'Performance Benchmark');
        });

        // New optimized streaming handlers
        $('#test-optimized-streaming').click(function() {
            const fileId = $('#file-id-input').val().trim();
            if (!fileId) {
                showStatus('error', 'Missing File ID', 'Please enter a Google Drive File ID');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-optimized-streaming', {
                file_id: fileId,
                rtmp_url: 'rtmp://live.youtube.com/live2',
                stream_key: 'YOUR_YOUTUBE_STREAM_KEY',
                video_bitrate: '2500k',
                resolution: '1280x720'
            }, 'Optimized Anti-Lag Streaming');
        });

        $('#test-streaming-health').click(function() {
            const fileId = $('#file-id-input').val().trim();
            if (!fileId) {
                showStatus('error', 'Missing File ID', 'Please enter a Google Drive File ID');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-streaming-health', {
                file_id: fileId
            }, 'Streaming Health Monitor');
        });

        $('#test-batch-performance').click(function() {
            const fileIds = $('#file-id-input').val().trim().split(',').map(id => id.trim()).filter(id => id);
            if (fileIds.length === 0) {
                showStatus('error', 'Missing File IDs', 'Please enter Google Drive File IDs (comma separated for multiple)');
                return;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-batch-performance', {
                file_ids: fileIds
            }, 'Batch Performance Comparison');
        });
        
        // Local FFmpeg test handlers
        $('#test-local-ffmpeg').click(function() {
            runStreamingTest($(this), '/test-google-drive/test-local-ffmpeg', {}, 'FFmpeg Installation Test');
        });
        
        $('#test-local-stream').click(function() {
            const fileId = $('#file-id-input').val().trim();
            const rtmpUrl = $('#test-rtmp-url').val().trim();
            const preset = $('#test-preset').val();
            
            const data = {
                preset: preset
            };
            
            if (fileId) {
                data.file_id = fileId;
            }
            
            if (rtmpUrl) {
                data.rtmp_url = rtmpUrl;
            }
            
            runStreamingTest($(this), '/test-google-drive/test-local-ffmpeg', data, 'Local FFmpeg Streaming Test');
        });

        function runAdvancedTest(btn, url, testName, method = 'POST') {
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Running...');
            
            const request = method === 'GET' ? $.get(url) : $.post(url);
            
            request.done(function(data) {
                showStatus('success', testName, 'Test completed successfully');
                displayAdvancedResult(data, testName);
            })
            .fail(function(xhr) {
                const error = xhr.responseJSON || {message: 'Test failed'};
                showStatus('error', testName + ' Failed', error.message);
                displayAdvancedResult({error: error.message}, testName);
            })
            .always(function() {
                btn.prop('disabled', false).html(originalText);
            });
        }

        function runStreamingTest(btn, url, data, testName) {
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Testing...');
            
            $.post(url, data)
                .done(function(response) {
                    showStatus('success', testName, 'Test completed successfully');
                    displayStreamingResult(response, testName);
                })
                .fail(function(xhr) {
                    const error = xhr.responseJSON || {message: 'Test failed'};
                    showStatus('error', testName + ' Failed', error.message);
                    displayStreamingResult({error: error.message}, testName);
                })
                .always(function() {
                    btn.prop('disabled', false).html(originalText);
                });
        }

        function displayAdvancedResult(data, testName) {
            const html = `
                <div class="border rounded-lg p-4 mb-4">
                    <h3 class="font-semibold text-lg mb-2">${testName} Results</h3>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto">${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
            $('#advanced-results').prepend(html);
        }

        function displayStreamingResult(data, testName) {
            const html = `
                <div class="border rounded-lg p-4 mb-4">
                    <h3 class="font-semibold text-lg mb-2">${testName} Results</h3>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-auto">${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
            $('#streaming-results').prepend(html);
        }

        // File actions
        function downloadFile(fileId) {
            showStatus('info', 'Downloading...', 'Please wait while file is being downloaded');
            
            $.post('/test-google-drive/download-file', {file_id: fileId})
                .done(function(data) {
                    if (data.status === 'success') {
                        showStatus('success', 'Download Complete', 'File downloaded successfully');
                    } else {
                        showStatus('error', 'Download Failed', data.message);
                    }
                })
                .fail(function(xhr) {
                    const error = xhr.responseJSON || {message: 'Download failed'};
                    showStatus('error', 'Download Failed', error.message);
                });
        }

        function deleteFile(fileId) {
            if (!confirm('Bạn có chắc chắn muốn xóa file này?')) {
                return;
            }
            
            showStatus('info', 'Deleting...', 'Please wait while file is being deleted');
            
            $.ajax({
                url: '/test-google-drive/delete-file',
                type: 'DELETE',
                data: {file_id: fileId},
                success: function(data) {
                    showStatus(data.status, 'Delete File', data.message);
                    if (data.status === 'success') {
                        $('#list-files').click(); // Refresh file list
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON || {message: 'Delete failed'};
                    showStatus('error', 'Delete Failed', error.message);
                }
            });
        }
    </script>
</body>
</html> 