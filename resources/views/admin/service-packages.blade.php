<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Quản Lý Gói Dịch Vụ') }}
        </h2>
    </x-slot>

    @livewire('service-package-manager')
</x-admin-layout> 