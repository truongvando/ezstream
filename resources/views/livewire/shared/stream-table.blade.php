<!-- Stream Management Table -->
<style>
    /* Ensure dropdowns don't get clipped */
    .dropdown-container {
        overflow: visible !important;
    }
    .dropdown-container * {
        overflow: visible !important;
    }
</style>
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg dropdown-container">
    <div class="p-6 text-gray-900 dark:text-gray-100">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Quản Lý Streams
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Tạo và quản lý các stream video của bạn
                </p>
            </div>
            
            @if(auth()->user()->isAdmin() || !isset($isAdmin))
            <button wire:click="create" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Tạo Stream Mới
            </button>
            @endif
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-4 mb-6">
            @if(auth()->user()->isAdmin())
            <div class="flex-1 min-w-48">
                <select wire:model="filterUserId" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Tất cả người dùng</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div class="flex-1 min-w-48">
                <select wire:model="filterStatus"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Tất cả trạng thái</option>
                    <option value="INACTIVE">Chưa hoạt động</option>
                    <option value="STARTING">Đang khởi động</option>
                    <option value="STREAMING">Đang phát</option>
                    <option value="STOPPING">Đang dừng</option>
                    <option value="STOPPED">Đã dừng</option>
                    <option value="ERROR">Lỗi</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto dropdown-container">
            <div class="relative dropdown-container">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Stream
                        </th>
                        @if(auth()->user()->isAdmin())
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Người dùng
                        </th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Máy chủ
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Thời gian
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($streams as $stream)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700" style="position: relative; overflow: visible;">
                            <!-- Stream Info -->
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $stream->title }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ Str::limit($stream->description, 50) }}
                                        </div>
                                        @if($stream->loop)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                24/7
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- User (Admin only) -->
                            @if(auth()->user()->isAdmin())
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-gray-100">
                                    {{ $stream->user->name }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $stream->user->email }}
                                </div>
                            </td>
                            @endif

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($stream->status)
                                            @case('STREAMING') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                            @case('STARTING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                            @case('STOPPING') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 @break
                                            @case('STOPPED') bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @break
                                            @case('ERROR') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                            @case('INACTIVE') bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @break
                                            @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @endswitch
                                    ">
                                        <div class="w-1.5 h-1.5 rounded-full mr-1.5
                                            @switch($stream->status)
                                                @case('STREAMING') bg-green-400 @break
                                                @case('STARTING') bg-yellow-400 animate-pulse @break
                                                @case('STOPPING') bg-orange-400 animate-pulse @break
                                                @case('ERROR') bg-red-400 @break
                                                @default bg-gray-400
                                            @endswitch
                                        "></div>
                                        @switch($stream->status)
                                            @case('STREAMING') Đang phát @break
                                            @case('STARTING') Đang khởi động @break
                                            @case('STOPPING') Đang dừng @break
                                            @case('STOPPED') Đã dừng @break
                                            @case('ERROR') Lỗi @break
                                            @case('INACTIVE') Chưa hoạt động @break
                                            @default {{ $stream->status }}
                                        @endswitch
                                    </span>

                                    @if($stream->status === 'ERROR' && $stream->error_message)
                                        <div class="mt-1 text-xs text-red-600 dark:text-red-400 max-w-xs">
                                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-2">
                                                <div class="flex items-start">
                                                    <svg class="w-3 h-3 text-red-500 mt-0.5 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span class="text-xs leading-tight">{{ Str::limit($stream->error_message, 100) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Server -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($stream->vpsServer)
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                        <span>Đang kết nối máy chủ</span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $stream->vpsServer->name }}
                                    </div>
                                @else
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                                        <span>Chưa phân bổ</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Time -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($stream->last_started_at)
                                    <div>Bắt đầu: {{ $stream->last_started_at->format('H:i d/m') }}</div>
                                @endif
                                @if($stream->scheduled_at)
                                    <div class="text-xs">Lịch: {{ $stream->scheduled_at->format('H:i d/m/Y') }}</div>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <!-- Start/Stop Button with Progress -->
                                    <div class="relative">
                                        @if(in_array($stream->status, ['STREAMING']))
                                            <!-- Stop Button -->
                                            <button wire:click="stopStream({{ $stream->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="inline-flex items-center px-4 py-2 border-2 border-red-600 text-sm font-semibold rounded-lg text-red-600 bg-white hover:bg-red-50 hover:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 shadow-sm">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z"></path>
                                                </svg>
                                                Dừng Stream
                                            </button>
                                        @elseif($stream->status === 'STARTING')
                                            <!-- Starting Button with Progress -->
                                            <div class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-sm font-semibold rounded-lg text-blue-600 bg-blue-50 cursor-not-allowed">
                                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
                                                <span id="progress-text-{{ $stream->id }}">Đang chuẩn bị...</span>
                                            </div>
                                            <!-- Progress Bar -->
                                            <div class="absolute top-full left-0 right-0 mt-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                <div id="progress-bar-{{ $stream->id }}" class="bg-blue-600 h-full rounded-full transition-all duration-300" style="width: 10%"></div>
                                            </div>
                                        @elseif(in_array($stream->status, ['PENDING', 'INACTIVE', 'STOPPED', 'COMPLETED', 'ERROR']))
                                            <!-- Start Button -->
                                            <button wire:click="startStream({{ $stream }})"
                                                    wire:loading.attr="disabled"
                                                    class="inline-flex items-center px-4 py-2 border-2 border-green-600 text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-700 hover:border-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 shadow-sm">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8m2-10v18a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2h8l4 4z"></path>
                                                </svg>
                                                Bắt đầu Stream
                                            </button>
                                        @elseif($stream->status === 'STOPPING')
                                            <!-- Stopping Button -->
                                            <div class="inline-flex items-center px-4 py-2 border-2 border-orange-600 text-sm font-semibold rounded-lg text-orange-600 bg-orange-50 cursor-not-allowed">
                                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-600 mr-2"></div>
                                                Đang dừng...
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Action Dropdown -->
                                    <div class="relative"
                                         x-data="{
                                             open: false,
                                             toggle() {
                                                 this.open = !this.open;
                                                 if (this.open) {
                                                     this.$nextTick(() => {
                                                         this.positionDropdown();
                                                     });
                                                 }
                                             },
                                             positionDropdown() {
                                                 const button = this.$refs.button;
                                                 const dropdown = this.$refs.dropdown;
                                                 if (button && dropdown) {
                                                     const rect = button.getBoundingClientRect();
                                                     dropdown.style.position = 'fixed';
                                                     dropdown.style.top = (rect.bottom + 4) + 'px';
                                                     dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                 }
                                             }
                                         }">
                                        <button @click="toggle()"
                                                x-ref="button"
                                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Thao tác
                                            <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        <!-- Fixed positioned dropdown -->
                                        <div x-show="open"
                                             x-ref="dropdown"
                                             @click.away="open = false"
                                             x-transition
                                             class="w-48 bg-white dark:bg-gray-700 rounded-md shadow-xl border border-gray-200 dark:border-gray-600"
                                             style="z-index: 9999; position: fixed;"
                                             @click="open = false">
                                            <div class="py-1">
                                                <button wire:click="edit({{ $stream->id }})" 
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Chỉnh sửa
                                                </button>
                                                
                                                @if(in_array($stream->status, ['STREAMING', 'STARTING']))
                                                    <button wire:click="updateLiveStream({{ $stream->id }})" 
                                                            class="block w-full text-left px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                        <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                        </svg>
                                                        Cập nhật trực tiếp
                                                    </button>
                                                @endif
                                                
                                                @if(auth()->user()->isAdmin())
                                                    <button wire:click="$dispatch('showLogModal', { streamId: {{ $stream->id }} })"
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                        <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        Xem nhật ký
                                                    </button>
                                                @endif
                                                
                                                <hr class="my-1 border-gray-200 dark:border-gray-600">
                                                
                                                <button wire:click="confirmDelete({{ $stream }})"
                                                        class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-600">
                                                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Xóa
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? '6' : '5' }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        </div>

        <!-- Pagination -->
        @if($streams->hasPages())
        <div class="mt-6">
            {{ $streams->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Progress Tracking Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Track streams that are starting
    const startingStreams = new Set();
    let isPolling = false;

    // Find all starting streams (only those with STARTING status)
    document.querySelectorAll('[id^="progress-text-"]').forEach(element => {
        const streamId = element.id.replace('progress-text-', '');
        console.log('Found progress element for stream:', streamId);

        // Always track streams with progress elements (they should be STARTING)
        startingStreams.add(streamId);
        console.log('Tracking progress for stream:', streamId);
    });

    // Poll progress for starting streams
    function pollProgress() {
        if (isPolling || startingStreams.size === 0) {
            return;
        }

        isPolling = true;
        console.log('Polling progress for streams:', Array.from(startingStreams));

        const promises = Array.from(startingStreams).map(streamId => {
            const url = `/api/stream/${streamId}/progress`;
            console.log(`Fetching progress from: ${url}`);

            return fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log(`Response status for stream ${streamId}:`, response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log(`Stream ${streamId} progress:`, data);
                    updateProgressUI(streamId, data);

                    // Remove from tracking if completed or error
                    if (data.stage === 'completed' || data.stage === 'error' || data.progress_percentage >= 100) {
                        console.log(`Stream ${streamId} completed, removing from tracking`);
                        startingStreams.delete(streamId);

                        // Refresh page after completion to show new status
                        setTimeout(() => {
                            console.log('Refreshing page after stream completion');
                            window.location.reload();
                        }, 3000);
                    }

                    return data;
                })
                .catch(error => {
                    console.error(`Error fetching progress for stream ${streamId}:`, error);
                    // Don't remove on error, try again next time
                });
        });

        Promise.all(promises).then(() => {
            isPolling = false;

            // Continue polling if there are streams to track
            if (startingStreams.size > 0) {
                setTimeout(pollProgress, 2000); // Poll every 2 seconds
            } else {
                console.log('No more streams to track, stopping polling');
            }
        });
    }

    function updateProgressUI(streamId, progressData) {
        const textElement = document.getElementById(`progress-text-${streamId}`);
        const barElement = document.getElementById(`progress-bar-${streamId}`);

        if (textElement) {
            textElement.textContent = progressData.message;
        }

        if (barElement) {
            barElement.style.width = progressData.progress_percentage + '%';

            // Change color based on stage
            if (progressData.stage === 'error') {
                barElement.className = 'bg-red-600 h-full rounded-full transition-all duration-300';
            } else if (progressData.stage === 'completed') {
                barElement.className = 'bg-green-600 h-full rounded-full transition-all duration-300';
            } else {
                barElement.className = 'bg-blue-600 h-full rounded-full transition-all duration-300';
            }
        }
    }

    // Start polling if there are starting streams
    if (startingStreams.size > 0) {
        console.log('Starting progress polling for', startingStreams.size, 'streams');
        setTimeout(pollProgress, 1000); // Start after 1 second
    } else {
        console.log('No starting streams found, skipping progress tracking');
    }

    // Prevent multiple script executions
    if (window.progressTrackingInitialized) {
        return;
    }
    window.progressTrackingInitialized = true;
});
</script>
