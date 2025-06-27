@props([
    'name' => '',
    'show' => false,
    'maxWidth' => '2xl',
    'closeable' => true,
    'title' => '',
])

@php
$maxWidthClasses = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div
    x-data="{ show: {{ $show ? 'true' : 'false' }} }"
    x-on:open-modal.window="$event.detail.name === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail.name === '{{ $name }}' ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-trap.inert.noscroll="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
>
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    <div x-show="show" class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidthClasses }} sm:mx-auto" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        @if ($title)
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $title }}</h2>
        </div>
        @endif
        
        <div class="px-6 py-4">
            {{ $slot }}
        </div>
        
        @if ($closeable)
        <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700 text-right">
             <x-secondary-button x-on:click="$dispatch('close-modal', { name: '{{ $name }}' })">
                {{ __('Close') }}
            </x-secondary-button>
        </div>
        @endif
    </div>
</div>
