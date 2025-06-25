<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Test Livestream - {{ $vps->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stream Controls -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Stream Controls</h3>
                    
                    <div class="flex space-x-4 mb-4">
                        <button id="startStream" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            ▶️ Start Test Stream
                        </button>
                        <button id="stopStream" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            ⏹️ Stop Test Stream
                        </button>
                        <button id="refreshStatus" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            🔄 Refresh Status
                        </button>
                    </div>
                    
                    <div id="streamStatus" class="mt-4 p-4 bg-gray-100 rounded">
                        <p class="text-sm">Status: <span id="statusText" class="font-bold">Ready</span></p>
                    </div>
                </div>
            </div>

            <!-- Stream URL -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Stream URL</h3>
                    <div class="bg-gray-100 p-4 rounded">
                        <p class="font-mono text-blue-600">{{ $streamUrl }}</p>
                        <p class="text-sm text-gray-600 mt-2">
                            Sử dụng URL này trong VLC Media Player: Media > Open Network Stream
                        </p>
                    </div>
                </div>
            </div>

            <!-- Server Info -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Server Information</h3>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold">Nginx Status:</h4>
                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto">{{ $nginxStatus }}</pre>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold">Videos Directory:</h4>
                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto">{{ $videosDir }}</pre>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold">FFmpeg Processes:</h4>
                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto">{{ $ffmpegProcesses ?: 'No FFmpeg processes running' }}</pre>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="font-semibold">Test Video:</h4>
                        <p class="text-sm">{{ $testVideoExists === 'EXISTS' ? '✅ Test video exists' : '❌ Test video not found' }}</p>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold">Manual Stream Command:</h4>
                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto">{{ $streamCommand }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('startStream').addEventListener('click', async () => {
            document.getElementById('statusText').textContent = 'Starting...';
            
            try {
                const response = await fetch(`/start-test-stream/{{ $vps->id }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('statusText').textContent = `Streaming (PID: ${data.pid})`;
                    alert(`Stream started!\n\nURL: ${data.stream_url}\n\nOpen this URL in VLC Media Player`);
                } else {
                    document.getElementById('statusText').textContent = 'Failed to start';
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                document.getElementById('statusText').textContent = 'Error';
                alert('Error: ' + error.message);
            }
        });
        
        document.getElementById('stopStream').addEventListener('click', async () => {
            document.getElementById('statusText').textContent = 'Stopping...';
            
            try {
                const response = await fetch(`/stop-test-stream/{{ $vps->id }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('statusText').textContent = 'Stopped';
                } else {
                    document.getElementById('statusText').textContent = 'Failed to stop';
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                document.getElementById('statusText').textContent = 'Error';
                alert('Error: ' + error.message);
            }
        });
        
        document.getElementById('refreshStatus').addEventListener('click', () => {
            window.location.reload();
        });
    </script>
</x-app-layout>
