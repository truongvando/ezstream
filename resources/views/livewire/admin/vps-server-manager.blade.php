<div wire:key="admin-vps-manager" wire:poll.5s>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Quản Lý VPS Servers') }}
        </h2>
    </x-slot>

    <div class="p-6">
        
        <!-- Header với nút Thêm trong component -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quản Lý VPS Servers</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Thêm và quản lý các VPS servers cho hệ thống streaming</p>
            </div>
            <a href="{{ route('vps.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Thêm VPS Server
            </a>
        </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- VPS Servers Table -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tên</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">System Load</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Provision Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SSH User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SSH Port</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hành động</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($servers as $server)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $server->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                {{ $server->ip_address }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                @if($server->latestStat)
                                    @php
                                        $ramUsage = $server->latestStat->ram_total_mb > 0 ? ($server->latestStat->ram_used_mb / $server->latestStat->ram_total_mb) * 100 : 0;
                                        $diskUsage = $server->latestStat->disk_total_gb > 0 ? ($server->latestStat->disk_used_gb / $server->latestStat->disk_total_gb) * 100 : 0;
                                    @endphp
                                    <div class="flex items-center mb-1">
                                        <span class="w-10 font-bold">CPU:</span>
                                        <span class="font-mono">{{ number_format($server->latestStat->cpu_load, 2) }}</span>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <span class="w-10">RAM:</span>
                                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700">
                                            <div class="bg-blue-600 h-4 rounded-full" style="width: {{ $ramUsage }}%"></div>
                                        </div>
                                        <span class="ml-2 text-xs font-medium">{{ number_format($ramUsage, 0) }}%</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-10">Disk:</span>
                                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700">
                                            <div class="bg-indigo-600 h-4 rounded-full" style="width: {{ $diskUsage }}%"></div>
                                        </div>
                                        <span class="ml-2 text-xs font-medium">{{ number_format($diskUsage, 0) }}%</span>
                                    </div>
                                @else
                                    <span class="text-gray-400">No data</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @switch($server->status)
                                        @case('ACTIVE') bg-green-100 text-green-800 @break
                                        @case('PROVISIONING') bg-yellow-100 text-yellow-800 @break
                                        @case('PROVISION_FAILED') bg-red-100 text-red-800 @break
                                        @default bg-gray-100 text-gray-800
                                    @endswitch
                                ">
                                    {{ $server->status }}
                                </span>
                                @if($server->status === 'PROVISIONING')
                                    <svg class="animate-spin h-4 w-4 text-gray-500 inline-block ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                {{ $server->ssh_user }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                {{ $server->ssh_port }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="toggleStatus({{ $server->id }})" 
                                        class="px-3 py-1 rounded-full text-xs font-medium {{ $server->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $server->is_active ? 'Hoạt động' : 'Tạm dừng' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.provision.status', ['vps' => $server->id]) }}" class="text-blue-600 hover:text-blue-900 mr-3">
                                    ⚙️ Provision
                                </a>
                                @if($server->status === 'ACTIVE')
                                <a href="/test-streaming/{{ $server->id }}" class="text-green-600 hover:text-green-900 mr-3">
                                    📺 Test Stream
                                </a>
                                <a href="{{ route('admin.streams') }}?test_vps={{ $server->id }}" class="text-purple-600 hover:text-purple-900 mr-3">
                                    🚀 Create Stream
                                </a>
                                @endif
                                <button wire:click="delete({{ $server->id }})" 
                                        wire:confirm="Bạn có chắc chắn muốn xóa VPS server này?"
                                        class="text-red-600 hover:text-red-900">
                                    Xóa
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                Chưa có VPS server nào được thêm.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-3">
            {{ $servers->links() }}
        </div>
    </div>

    <!-- All modals have been removed from this component for simplification -->
    <!-- The Add functionality now redirects to a dedicated page -->
    <!-- Log viewing functionality can be re-added via a dedicated page if needed -->

    </div>
</div>
