<x-sidebar-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            🔑 Quản lý License
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        @livewire('license-manager')
    </div>
</x-sidebar-layout>
