<div>
    @if($isVisible && $progress)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="update-progress-modal">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            🔄 Cập nhật Agent VPS #{{ $vpsId }}
                        </h3>
                        @if($progress['progress_percentage'] >= 100)
                            <button wire:click="hideUpdateProgress" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        @endif
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                {{ $progress['message'] }}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $progress['progress_percentage'] }}%
                            </span>
                        </div>
                        
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            @php
                                $progressColor = 'bg-blue-600';
                                if ($progress['stage'] === 'error') {
                                    $progressColor = 'bg-red-600';
                                } elseif ($progress['progress_percentage'] >= 100) {
                                    $progressColor = 'bg-green-600';
                                }
                            @endphp
                            
                            <div class="{{ $progressColor }} h-3 rounded-full transition-all duration-500 ease-out"
                                 style="width: {{ $progress['progress_percentage'] }}%">
                            </div>
                        </div>
                    </div>

                    <!-- Stage Info -->
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div class="flex items-center">
                            @if($progress['stage'] === 'error')
                                <svg class="w-4 h-4 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-red-600 dark:text-red-400">Có lỗi xảy ra</span>
                            @elseif($progress['progress_percentage'] >= 100)
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-green-600 dark:text-green-400">Hoàn thành</span>
                            @else
                                <svg class="w-4 h-4 text-blue-500 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span>Đang xử lý...</span>
                            @endif
                        </div>
                        
                        <div class="mt-2 text-xs">
                            Cập nhật lúc: {{ \Carbon\Carbon::parse($progress['updated_at'])->format('H:i:s') }}
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    @if($progress['progress_percentage'] >= 100 || $progress['stage'] === 'error')
                        <div class="flex justify-end">
                            <button wire:click="hideUpdateProgress" 
                                    class="px-4 py-2 bg-gray-500 text-white text-sm rounded-md hover:bg-gray-600 transition-colors">
                                Đóng
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    // Auto-refresh every 2 seconds when modal is visible
    document.addEventListener('livewire:initialized', () => {
        let refreshInterval;
        
        Livewire.on('showUpdateProgress', () => {
            refreshInterval = setInterval(() => {
                @this.loadProgress();
            }, 1000);
        });
        
        Livewire.on('hideUpdateProgress', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
        
        Livewire.on('update-completed', () => {
            setTimeout(() => {
                @this.hideUpdateProgress();
            }, 3000); // Auto-hide after 3 seconds
        });
    });
</script>
