<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Quản Lý VPS Servers') }}
            </h2>
            <button wire:click="openModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                Thêm VPS Server
            </button>
        </div>
    </x-slot>

    <div class="p-6">

    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
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
                                <button wire:click="edit({{ $server->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    Sửa
                                </button>
                                <button wire:click="delete({{ $server->id }})" 
                                        wire:confirm="Bạn có chắc chắn muốn xóa VPS server này?"
                                        class="text-red-600 hover:text-red-900">
                                    Xóa
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
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

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        {{ $editingServer ? 'Cập nhật VPS Server' : 'Thêm VPS Server' }}
                    </h3>
                    
                    <form wire:submit="save">
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" value="Tên" />
                                <x-text-input id="name" wire:model="name" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="ip_address" value="IP Address" />
                                <x-text-input id="ip_address" wire:model="ip_address" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('ip_address')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="ssh_user" value="SSH User" />
                                <x-text-input id="ssh_user" wire:model="ssh_user" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('ssh_user')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="ssh_password" value="SSH Password" />
                                <x-text-input id="ssh_password" wire:model="ssh_password" type="password" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('ssh_password')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="ssh_port" value="SSH Port" />
                                <x-text-input id="ssh_port" wire:model="ssh_port" type="number" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('ssh_port')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" value="Mô tả" />
                                <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div class="block">
                                <label for="is_active" class="flex items-center">
                                    <x-checkbox id="is_active" wire:model="is_active" />
                                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Server hoạt động</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <x-secondary-button type="button" wire:click="closeModal">
                                Hủy
                            </x-secondary-button>
                            <x-primary-button type="submit">
                                {{ $editingServer ? 'Cập nhật' : 'Thêm' }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
