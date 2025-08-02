<x-sidebar-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            🛠️ Chi tiết Tool
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @livewire('tool-detail', ['slug' => $slug])
    </div>
</x-sidebar-layout>
