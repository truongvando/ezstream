<div>
    <div class="space-y-6">
        <!-- Flash Message -->
        @if (session()->has('message'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                {{ session('message') }}
            </div>
        @endif

    <!-- Control Panel -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">VPS Monitoring Control</h2>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Auto Refresh Toggle -->
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model.live="autoRefresh" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Auto Refresh ({{ $refreshInterval }}s)</span>
                </label>
                
                <!-- Real-time Toggle -->
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model.live="showRealTime" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Real-time Stats</span>
                </label>
                
                <!-- Refresh Button -->
                <button wire:click="refreshNow" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    🔄 Refresh Now
                </button>
            </div>
        </div>
    </div>

    <!-- VPS Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($vpsServers as $vps)
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md cursor-pointer transition-all hover:shadow-lg {{ $selectedVps == $vps['id'] ? 'ring-2 ring-blue-500' : '' }}"
                 wire:click="selectVps({{ $vps['id'] }})">
                
                <!-- VPS Header -->
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $vps['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $vps['ip_address'] }}</p>
                        
                        <!-- Webhook Status Indicator -->
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $vps['webhook_status'] === 'active' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $vps['webhook_status'] === 'active' ? '📡 Webhook Active' : '🔌 SSH Only' }}
                            </span>
                            
                            @if($showRealTime && isset($vps['data_source']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs {{ $vps['data_source'] === 'webhook' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($vps['data_source'] === 'ssh_fallback' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                    @if($vps['data_source'] === 'webhook')
                                        ⚡ Live Data
                                    @elseif($vps['data_source'] === 'ssh_fallback')
                                        🔄 SSH Fallback
                                    @else
                                        ❌ No Data
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $vps['status'] === 'online' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                            {{ $vps['status'] === 'online' ? '🟢 Online' : '🔴 Offline' }}
                        </span>
                        <span class="text-xs text-gray-400 mt-1">{{ $vps['last_updated'] }}</span>
                    </div>
                </div>

                <!-- Real-time Alert -->
                @if($showRealTime && isset($vps['error']))
                    <div class="mb-3 p-2 bg-red-50 dark:bg-red-900/20 rounded text-xs text-red-600 dark:text-red-400">
                        SSH Error: {{ $vps['error'] }}
                    </div>
                @endif

                <!-- Resource Usage Bars -->
                <div class="space-y-3">
                    <!-- CPU -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">CPU</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                @if($showRealTime && isset($vps['realtime_stats']['cpu']))
                                    {{ number_format($vps['realtime_stats']['cpu'], 1) }}% 
                                    <span class="text-xs text-blue-500">(live)</span>
                                @else
                                    {{ number_format($vps['cpu_usage_percent'], 1) }}%
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @php
                                $cpuValue = $showRealTime && isset($vps['realtime_stats']['cpu']) 
                                    ? $vps['realtime_stats']['cpu'] 
                                    : $vps['cpu_usage_percent'];
                                $cpuColor = $cpuValue > 80 ? 'bg-red-500' : ($cpuValue > 60 ? 'bg-yellow-500' : 'bg-blue-500');
                            @endphp
                            <div class="{{ $cpuColor }} h-2 rounded-full transition-all duration-500" style="width: {{ $cpuValue }}%"></div>
                        </div>
                    </div>

                    <!-- RAM -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">RAM</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                @if($showRealTime && isset($vps['realtime_stats']['ram']))
                                    {{ number_format($vps['realtime_stats']['ram'], 1) }}% 
                                    <span class="text-xs text-blue-500">(live)</span>
                                @else
                                    {{ number_format($vps['ram_usage_percent'], 1) }}%
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @php
                                $ramValue = $showRealTime && isset($vps['realtime_stats']['ram']) 
                                    ? $vps['realtime_stats']['ram'] 
                                    : $vps['ram_usage_percent'];
                                $ramColor = $ramValue > 85 ? 'bg-red-500' : ($ramValue > 70 ? 'bg-yellow-500' : 'bg-green-500');
                            @endphp
                            <div class="{{ $ramColor }} h-2 rounded-full transition-all duration-500" style="width: {{ $ramValue }}%"></div>
                        </div>
                    </div>

                    <!-- Disk -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Disk</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                @if($showRealTime && isset($vps['realtime_stats']['disk']))
                                    {{ number_format($vps['realtime_stats']['disk'], 1) }}% 
                                    <span class="text-xs text-blue-500">(live)</span>
                                @else
                                    {{ number_format($vps['disk_usage_percent'], 1) }}%
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            @php
                                $diskValue = $showRealTime && isset($vps['realtime_stats']['disk']) 
                                    ? $vps['realtime_stats']['disk'] 
                                    : $vps['disk_usage_percent'];
                                $diskColor = $diskValue > 90 ? 'bg-red-500' : ($diskValue > 75 ? 'bg-yellow-500' : 'bg-purple-500');
                            @endphp
                            <div class="{{ $diskColor }} h-2 rounded-full transition-all duration-500" style="width: {{ $diskValue }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- Mini Historical Chart -->
                @if(count($vps['historical_stats']) > 0)
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="text-xs text-gray-500 dark:text-gray-400 mb-2">Recent History (CPU)</h4>
                        <div class="flex items-end space-x-1 h-8">
                            @foreach($vps['historical_stats']->reverse() as $stat)
                                <div class="bg-blue-200 dark:bg-blue-700 rounded-sm w-2" 
                                     style="height: {{ max(2, $stat['cpu']) }}%"
                                     title="CPU: {{ $stat['cpu'] }}% at {{ $stat['time'] }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md text-center">
                <p class="text-gray-500 dark:text-gray-400">No active VPS servers found.</p>
            </div>
        @endforelse
    </div>

    <!-- Detailed View for Selected VPS -->
    @if($selectedVpsData)
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                Detailed View: {{ $selectedVpsData['name'] }}
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- CPU Chart -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">CPU Usage History</h4>
                    <div class="h-32 flex items-end space-x-1">
                        @forelse($selectedVpsData['historical_stats'] as $stat)
                            <div class="bg-blue-500 rounded-sm flex-1" 
                                 style="height: {{ max(5, $stat['cpu']) }}%"
                                 title="CPU: {{ $stat['cpu'] }}% at {{ $stat['time'] }}">
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No historical data</p>
                        @endforelse
                    </div>
                </div>

                <!-- RAM Chart -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">RAM Usage History</h4>
                    <div class="h-32 flex items-end space-x-1">
                        @forelse($selectedVpsData['historical_stats'] as $stat)
                            <div class="bg-green-500 rounded-sm flex-1" 
                                 style="height: {{ max(5, $stat['ram']) }}%"
                                 title="RAM: {{ $stat['ram'] }}% at {{ $stat['time'] }}">
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No historical data</p>
                        @endforelse
                    </div>
                </div>

                <!-- Disk Chart -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Disk Usage History</h4>
                    <div class="h-32 flex items-end space-x-1">
                        @forelse($selectedVpsData['historical_stats'] as $stat)
                            <div class="bg-purple-500 rounded-sm flex-1" 
                                 style="height: {{ max(5, $stat['disk']) }}%"
                                 title="Disk: {{ $stat['disk'] }}% at {{ $stat['time'] }}">
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No historical data</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Real-time Status Indicator -->
    <div class="fixed bottom-4 right-4 z-50">
        @if($autoRefresh)
            <div class="bg-green-500 text-white px-3 py-1 rounded-full text-xs flex items-center space-x-2">
                <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                <span>Live Monitoring</span>
            </div>
        @endif
        
        @if($showRealTime)
            <div class="bg-blue-500 text-white px-3 py-1 rounded-full text-xs mt-2 flex items-center space-x-2">
                <div class="w-2 h-2 bg-white rounded-full animate-ping"></div>
                <span>Real-time Stats</span>
            </div>
        @endif
    </div>

    <!-- Auto-refresh Script with Enhanced Polling -->
    @if($autoRefresh)
    <script>
        let refreshInterval;
        
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            
            refreshInterval = setInterval(function() {
                if (@this.autoRefresh) {
                    @this.call('$refresh');
                    
                    // If real-time is enabled, also trigger stats sync
                    if (@this.showRealTime) {
                        @this.call('ensureFreshStats');
                    }
                }
            }, {{ $refreshInterval * 1000 }});
        }
        
        // Start on page load
        startAutoRefresh();
        
        // Restart when settings change
        document.addEventListener('livewire:updated', function () {
            if (@this.autoRefresh) {
                startAutoRefresh();
            } else {
                if (refreshInterval) clearInterval(refreshInterval);
            }
        });
        
        // Page visibility API - pause when tab is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (refreshInterval) clearInterval(refreshInterval);
            } else if (@this.autoRefresh) {
                startAutoRefresh();
            }
        });
    </script>
    @endif
</div> 