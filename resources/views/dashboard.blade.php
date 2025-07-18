<x-app-layout>
    <div class="py-4 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(auth()->user()->isAdmin())
                @livewire('admin.dashboard')
            @else
                @livewire('dashboard')
            @endif
        </div>
    </div>
</x-app-layout>
