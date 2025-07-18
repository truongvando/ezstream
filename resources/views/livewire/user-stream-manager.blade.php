<div wire:poll.3s="refreshStreams">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Debug info (remove in production) -->
            <div class="mb-2 text-xs text-gray-500">
                Last refresh: {{ now()->format('H:i:s') }} | Polling every 3s
            </div>

            @include('livewire.shared.stream-cards')
            @include('livewire.shared.stream-form-modal')
            @include('livewire.shared.quick-stream-modal')
        </div>
    </div>
</div>
