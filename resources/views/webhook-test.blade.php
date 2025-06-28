<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Testing Interface</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">🧪 Webhook Testing Interface</h1>
            
            <!-- Quick Test Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold mb-4">📋 Recent Streams</h2>
                    @forelse($streams as $stream)
                        <div class="mb-3 p-3 bg-white rounded border">
                            <div class="flex justify-between items-center">
                                <div>
                                    <strong>ID: {{ $stream->id }}</strong> - {{ $stream->title }}
                                    <div class="text-sm text-gray-600">Status: {{ $stream->status }}</div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="quickTest({{ $stream->id }}, 'STREAMING')" 
                                            class="bg-green-500 text-white px-2 py-1 rounded text-xs">
                                        STREAMING
                                    </button>
                                    <button onclick="quickTest({{ $stream->id }}, 'STOPPED')" 
                                            class="bg-red-500 text-white px-2 py-1 rounded text-xs">
                                        STOPPED
                                    </button>
                                    <button onclick="quickTest({{ $stream->id }}, 'ERROR')" 
                                            class="bg-gray-500 text-white px-2 py-1 rounded text-xs">
                                        ERROR
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">No streams found. Create a stream first.</p>
                    @endforelse
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <h2 class="text-xl font-semibold mb-4">🎯 Custom Webhook Test</h2>
                    <form id="webhookForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Stream ID</label>
                            <input type="number" id="streamId" class="w-full border rounded px-3 py-2" placeholder="Enter stream ID" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Status</label>
                            <select id="status" class="w-full border rounded px-3 py-2">
                                <option value="DOWNLOADING">DOWNLOADING</option>
                                <option value="STREAMING" selected>STREAMING</option>
                                <option value="RECOVERING">RECOVERING</option>
                                <option value="STOPPED">STOPPED</option>
                                <option value="COMPLETED">COMPLETED</option>
                                <option value="ERROR">ERROR</option>
                                <option value="HEARTBEAT">HEARTBEAT</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Message</label>
                            <textarea id="message" class="w-full border rounded px-3 py-2" rows="3" placeholder="Custom message (optional)"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                            🚀 Send Webhook
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Response Display -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h2 class="text-xl font-semibold mb-4">📡 Response Log</h2>
                <div id="responseLog" class="bg-black text-green-400 p-4 rounded font-mono text-sm h-64 overflow-y-auto">
                    <div class="text-gray-500">Webhook responses will appear here...</div>
                </div>
                <button onclick="clearLog()" class="mt-2 bg-red-500 text-white px-4 py-2 rounded text-sm">
                    Clear Log
                </button>
            </div>
        </div>
    </div>

    <script>
        function logResponse(message, type = 'info') {
            const log = document.getElementById('responseLog');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'info': 'text-blue-400',
                'success': 'text-green-400', 
                'error': 'text-red-400',
                'warning': 'text-yellow-400'
            };
            
            const entry = `<div class="${colors[type] || 'text-gray-400'}">[${timestamp}] ${message}</div>`;
            log.innerHTML += entry;
            log.scrollTop = log.scrollHeight;
        }
        
        function quickTest(streamId, status) {
            logResponse(`🚀 Sending ${status} webhook for stream ${streamId}...`, 'info');
            
            fetch(`/webhook-test/quick/${streamId}/${status}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    logResponse(`✅ SUCCESS: ${data.message}`, 'success');
                    logResponse(`📊 Response: ${JSON.stringify(data.response_body)}`, 'info');
                } else {
                    logResponse(`❌ ERROR: ${data.error || data.message}`, 'error');
                }
            })
            .catch(error => {
                logResponse(`❌ FETCH ERROR: ${error.message}`, 'error');
            });
        }
        
        function clearLog() {
            document.getElementById('responseLog').innerHTML = '<div class="text-gray-500">Webhook responses will appear here...</div>';
        }
        
        document.getElementById('webhookForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const streamId = document.getElementById('streamId').value;
            const status = document.getElementById('status').value;
            const message = document.getElementById('message').value;
            
            logResponse(`🚀 Sending custom webhook for stream ${streamId}...`, 'info');
            
            const formData = new FormData();
            formData.append('stream_id', streamId);
            formData.append('status', status);
            if (message) formData.append('message', message);
            
            fetch('/webhook-test/simulate', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    logResponse(`✅ SUCCESS: ${data.message}`, 'success');
                    logResponse(`📊 Webhook Data: ${JSON.stringify(data.webhook_data)}`, 'info');
                    logResponse(`📡 Response: ${JSON.stringify(data.response_body)}`, 'info');
                } else {
                    logResponse(`❌ ERROR: ${data.error || data.message}`, 'error');
                }
            })
            .catch(error => {
                logResponse(`❌ FETCH ERROR: ${error.message}`, 'error');
            });
        });
    </script>
</body>
</html> 