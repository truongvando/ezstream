<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Stream Debug Dashboard
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Real-time monitoring và debugging cho streams
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <button wire:click="toggleAutoRefresh" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium {{ $autoRefresh ? 'text-green-700 bg-green-50 border-green-300' : 'text-gray-700 bg-white' }} dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <svg class="w-4 h-4 mr-2 {{ $autoRefresh ? 'animate-spin' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    {{ $autoRefresh ? 'Auto Refresh ON' : 'Auto Refresh OFF' }}
                </button>
                
                <button wire:click="refreshData" 
                        class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Stream Metrics -->
    @if(!empty($streamMetrics))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Active Streams Health</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($streamMetrics as $metric)
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 {{ $selectedStream == $metric['id'] ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' }}"
                 wire:click="selectStream({{ $metric['id'] }})">
                <div class="flex items-center justify-between">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $metric['title'] }}</h4>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                        {{ $metric['status'] === 'STREAMING' ? 'bg-green-100 text-green-800' : 
                           ($metric['status'] === 'STARTING' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                        {{ $metric['status'] }}
                    </span>
                </div>
                <div class="mt-2 space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Health Score:</span>
                        <span class="font-medium {{ $metric['health_score'] >= 80 ? 'text-green-600' : ($metric['health_score'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $metric['health_score'] }}%
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Errors (1h):</span>
                        <span class="font-medium {{ $metric['error_count'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $metric['error_count'] }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Last: {{ $metric['last_activity'] ?? 'No activity' }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-wrap items-center gap-4">
            <!-- Stream Filter -->
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stream:</label>
                <select wire:model.live="selectedStream" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md">
                    <option value="">All Streams</option>
                    @foreach($userStreams as $stream)
                    <option value="{{ $stream->id }}">{{ $stream->title }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Level Filter -->
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Level:</label>
                <select wire:model.live="selectedLevel" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md">
                    <option value="all">All Levels</option>
                    <option value="DEBUG">Debug</option>
                    <option value="INFO">Info</option>
                    <option value="WARNING">Warning</option>
                    <option value="ERROR">Error</option>
                    <option value="CRITICAL">Critical</option>
                </select>
            </div>

            <!-- Category Filter -->
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Category:</label>
                <select wire:model.live="selectedCategory" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md">
                    <option value="all">All Categories</option>
                    <option value="STREAM_LIFECYCLE">Stream Lifecycle</option>
                    <option value="PLAYLIST_MANAGEMENT">Playlist Management</option>
                    <option value="QUALITY_MONITORING">Quality Monitoring</option>
                    <option value="ERROR_RECOVERY">Error Recovery</option>
                    <option value="AGENT_COMMUNICATION">Agent Communication</option>
                    <option value="PERFORMANCE">Performance</option>
                    <option value="USER_ACTION">User Action</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-center space-x-2 ml-auto">
                <button wire:click="exportLogs" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export
                </button>
                
                <button wire:click="clearLogs" 
                        onclick="return confirm('Are you sure you want to clear logs?')"
                        class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Stream Logs</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stream</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Context</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            {{ $log->created_at->format('H:i:s') }}
                            <div class="text-xs text-gray-500">{{ $log->created_at->format('M d') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            @if($log->stream_id)
                                <span class="font-medium">#{{ $log->stream_id }}</span>
                                @if($log->stream)
                                    <div class="text-xs text-gray-500 truncate max-w-32">{{ $log->stream->title }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">System</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $log->level === 'ERROR' || $log->level === 'CRITICAL' ? 'bg-red-100 text-red-800' : 
                                   ($log->level === 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($log->level === 'INFO' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) }}">
                                {{ $log->level }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ str_replace('_', ' ', $log->category) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                            <div class="max-w-xs truncate">{{ $log->event }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            @if($log->context)
                                <details class="cursor-pointer">
                                    <summary class="text-blue-600 hover:text-blue-800">View Context</summary>
                                    <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded overflow-x-auto">{{ json_encode($log->context, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @else
                                <span class="text-gray-400">No context</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p>No logs found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $logs->links() }}
        </div>
    </div>

    @if($autoRefresh)
    <script>
        setInterval(function() {
            @this.call('refreshData');
        }, {{ $refreshInterval * 1000 }});
    </script>
    @endif
</div>
