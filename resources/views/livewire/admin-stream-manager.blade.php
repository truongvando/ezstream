<div>
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">

        <!-- Create Button -->
        @if(auth()->user()->isAdmin())
        <div class="mb-4 flex justify-end">
            <button wire:click="create" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Tạo Stream Mới
            </button>
        </div>
        @endif

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
             <div>
                <label for="filterUserId" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by User</label>
                <select id="filterUserId" wire:model="filterUserId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Status</label>
                <select id="filterStatus" wire:model="filterStatus" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Streams Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">VPS</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($streams as $stream)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $stream->user->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $stream->title }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($stream->description, 40) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $stream->vpsServer ? $stream->vpsServer->name : 'Auto-assign' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
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
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    @if(in_array($stream->status, ['ACTIVE', 'STREAMING', 'STARTING']))
                                        <button class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-200" wire:click="stopStream({{ $stream->id }})" wire:loading.attr="disabled">
                                            {{ $stream->status === 'STARTING' ? 'Starting...' : 'Stop' }}
                                        </button>
                                    @elseif(in_array($stream->status, ['INACTIVE', 'STOPPED', 'ERROR']))
                                        <button class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200" wire:click="startStream({{ $stream->id }})" wire:loading.attr="disabled">
                                            Start
                                        </button>
                                    @elseif($stream->status === 'STOPPING')
                                        <button class="text-gray-400" disabled>Stopping...</button>
                                        <button class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 text-xs" wire:click="forceStopStream({{ $stream->id }})" title="Force stop if stuck">
                                            Force Stop
                                        </button>
                                    @endif
                                    <button class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200" wire:click="edit({{ $stream->id }})">Edit</button>
                                    <button class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200" wire:click="confirmDelete({{ $stream->id }})">Delete</button>
                                    <button class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" wire:click="$dispatch('showLogModal', { streamId: {{ $stream->id }} })">View Log</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có stream nào</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream đầu tiên.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $streams->links() }}
        </div>
    </div>
</div>

<!-- Modals -->
@include('livewire.admin.partials.stream-form-modal')

<!-- Delete Modal -->
<x-modal-v2 wire:model.live="showDeleteModal" max-width="md">
    <div class="p-6">
        <div class="flex items-center mb-4">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
        </div>
        <div class="text-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Xóa Stream</h3>
            @if($deletingStream)
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Bạn có chắc chắn muốn xóa stream "<strong>{{ $deletingStream->title }}</strong>"? Hành động này không thể hoàn tác.
                </p>
            @endif
            <div class="flex justify-center space-x-3">
                <x-secondary-button wire:click="$set('showDeleteModal', false)">Hủy</x-secondary-button>
                <x-danger-button wire:click="delete">Xóa Stream</x-danger-button>
            </div>
        </div>
    </div>
</x-modal-v2>

@livewire('log-viewer-modal')
</div>
