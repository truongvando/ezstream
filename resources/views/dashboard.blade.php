<x-app-layout>
    <div class="h-full">
        @if(auth()->user()->isAdmin())
            @livewire('admin.dashboard')
        @else
            @livewire('dashboard')
        @endif
    </div>
</x-app-layout>
