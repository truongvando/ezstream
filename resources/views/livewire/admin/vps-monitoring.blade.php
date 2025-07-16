<div wire:poll.5s>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($vpsServers as $vps)
            <div @class([
                'p-6 rounded-lg shadow-lg transition-all duration-300',
                'bg-white dark:bg-gray-800' => $vps['is_online'],
                'bg-gray-200 dark:bg-gray-700 opacity-60' => !$vps['is_online'],
            ])>
                
                <!-- Header -->
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $vps['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $vps['ip_address'] }}</p>
                    </div>
                    <span @class([
                        'inline-flex items-center px-3 py-1 rounded-full text-xs font-bold',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $vps['is_online'],
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => !$vps['is_online'],
                    ])>
                        {{ $vps['status'] }}
                    </span>
                </div>

                <!-- Stats -->
                <div class="space-y-4">
                    <!-- CPU -->
                    <div>
                        <div class="flex justify-between mb-1 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">CPU</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($vps['cpu_usage_percent'], 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-2.5">
                            @php($cpuColor = $vps['cpu_usage_percent'] > 80 ? 'bg-red-500' : ($vps['cpu_usage_percent'] > 60 ? 'bg-yellow-400' : 'bg-blue-500'))
                            <div class="{{ $cpuColor }} h-2.5 rounded-full transition-all duration-500" style="width: {{ $vps['cpu_usage_percent'] }}%"></div>
                        </div>
                    </div>

                    <!-- RAM -->
                    <div>
                        <div class="flex justify-between mb-1 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">RAM</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($vps['ram_usage_percent'], 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-2.5">
                            @php($ramColor = $vps['ram_usage_percent'] > 85 ? 'bg-red-500' : ($vps['ram_usage_percent'] > 70 ? 'bg-yellow-400' : 'bg-green-500'))
                            <div class="{{ $ramColor }} h-2.5 rounded-full transition-all duration-500" style="width: {{ $vps['ram_usage_percent'] }}%"></div>
                        </div>
                    </div>
                    
                    <!-- Disk -->
                    <div>
                        <div class="flex justify-between mb-1 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Disk</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($vps['disk_usage_percent'], 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-2.5">
                            @php($diskColor = $vps['disk_usage_percent'] > 90 ? 'bg-red-500' : ($vps['disk_usage_percent'] > 75 ? 'bg-yellow-400' : 'bg-purple-500'))
                            <div class="{{ $diskColor }} h-2.5 rounded-full transition-all duration-500" style="width: {{ $vps['disk_usage_percent'] }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-5 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-sm">
                    <div class="text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">Streams:</span>
                        <span class="font-mono">{{ $vps['current_streams'] }} / {{ $vps['max_streams'] }}</span>
                    </div>
                    <div class="text-gray-500 dark:text-gray-400" title="{{ $vps['last_updated'] }}">
                        Updated: {{ $vps['last_updated'] }}
                    </div>
                </div>

            </div>
        @empty
            <div class="col-span-full bg-white dark:bg-gray-800 p-12 rounded-lg shadow-md text-center">
                <h3 class="text-xl font-medium text-gray-900 dark:text-white">No VPS Servers Found</h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Add a new VPS server to start monitoring.</p>
            </div>
        @endforelse
    </div>
</div> 