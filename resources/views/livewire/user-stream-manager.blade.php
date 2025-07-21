<div>
    {{-- Use shared layout instead of duplicating includes --}}
    @include('livewire.shared.stream-manager-layout', [
        'isAdmin' => false,
        'streams' => $streams,
        'hasActiveStreams' => $this->hasActiveStreams
    ])
</div>


