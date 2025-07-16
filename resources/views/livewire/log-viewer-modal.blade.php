<div wire:poll.5s>
    <!-- Log Viewer Modal -->
    <x-modal-v2 wire:model.live="showModal" max-width="4xl">
        <div class="p-6">
            @if($stream)
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            📋 Stream Logs
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Stream: <strong>{{ $stream->title }}</strong>
                            @if($stream->vpsServer)
                                • VPS: <strong>{{ $stream->vpsServer->name }}</strong> ({{ $stream->vpsServer->ip_address }})
                            @else
                                • VPS: <span class="text-yellow-600">Auto-assign</span>
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeModal" 
                            class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded p-1 transition-colors"
                            title="Đóng">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="mb-4 flex justify-between items-center">
                    <div class="flex space-x-2">
                        <button wire:click="loadLogContent" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Refresh Logs
                        </button>
                        
                        @if($stream->vpsServer)
                            <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                VPS Connected
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                No VPS Assigned
                            </span>
                        @endif
                    </div>
                    
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Status: <span class="px-2 py-1 rounded-full text-xs font-medium
                            @switch($stream->status)
                                @case('ACTIVE')
                                @case('STREAMING') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                @case('INACTIVE')
                                @case('STOPPED') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                @case('ERROR') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                @case('STARTING')
                                @case('STOPPING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                            @endswitch
                        ">{{ $stream->status }}</span>
                    </div>
                </div>
                
                <!-- Log Content Display -->
                <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm max-h-96 overflow-y-auto border border-gray-700">
                    @if($stream->vpsServer)
                        @if($logContent === '')
                            <div class="flex items-center justify-center h-32">
                                <div class="text-center">
                                    <svg class="mx-auto h-8 w-8 text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-gray-500">Click "Refresh Logs" để tải log files</p>
                                </div>
                            </div>
                        @else
                            <pre class="whitespace-pre-wrap">{{ $logContent }}</pre>
                        @endif
                    @else
                        <div class="flex items-center justify-center h-32">
                            <div class="text-center">
                                <svg class="mx-auto h-8 w-8 text-yellow-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <p class="text-yellow-500">Stream chưa có VPS được assign</p>
                                <p class="text-gray-500 text-xs mt-1">VPS sẽ được tự động assign khi stream bắt đầu</p>
                            </div>
                        </div>
                    @endif
                </div>
                
                <!-- Stream Info -->
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">📺 Stream Info</h4>
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Platform:</dt>
                                <dd class="font-medium">
                                    @if(str_contains($stream->rtmp_url, 'youtube'))
                                        📺 YouTube
                                    @elseif(str_contains($stream->rtmp_url, 'facebook'))
                                        📘 Facebook
                                    @elseif(str_contains($stream->rtmp_url, 'twitch'))
                                        🎮 Twitch
                                    @else
                                        ⚙️ Custom
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Preset:</dt>
                                <dd class="font-medium">{{ $stream->stream_preset ?? 'direct' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Loop:</dt>
                                <dd class="font-medium">{{ $stream->loop ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">🕒 Timestamps</h4>
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Created:</dt>
                                <dd class="font-medium">{{ $stream->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                            @if($stream->last_started_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-600 dark:text-gray-400">Last Started:</dt>
                                    <dd class="font-medium">{{ $stream->last_started_at->format('Y-m-d H:i') }}</dd>
                                </div>
                            @endif
                            @if($stream->last_stopped_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-600 dark:text-gray-400">Last Stopped:</dt>
                                    <dd class="font-medium">{{ $stream->last_stopped_at->format('Y-m-d H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
                
            @else
                <div class="text-center py-8">
                    <p class="text-gray-500 dark:text-gray-400">Stream not found</p>
                </div>
            @endif
        </div>
    </x-modal-v2>
</div>
