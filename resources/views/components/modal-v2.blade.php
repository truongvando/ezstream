@props(['show' => false, 'maxWidth' => '2xl', 'name' => 'modal'])

@php
$maxWidthClasses = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
    '7xl' => 'sm:max-w-7xl',
][$maxWidth];

// Check if we have a wire:model attribute
$wireModel = $attributes->get('wire:model');
if ($wireModel === null) {
    $wireModel = $attributes->get('wire:model.live');
}
$hasWireModel = !empty($wireModel);
@endphp

<div
    @if($hasWireModel)
        x-data="{ show: @entangle($wireModel) }"
    @else
        x-data="{ show: {{ $show ? 'true' : 'false' }} }"
        x-on:open-modal.window="$event.detail.name === '{{ $name }}' ? show = true : null"
        x-on:close-modal.window="$event.detail.name === '{{ $name }}' ? show = false : null"
        x-on:close.stop="show = false"
        x-on:open-modal-{{ $name }}.window="show = true"
        x-on:close-modal-{{ $name }}.window="show = false"
    @endif
    x-show="show"
    x-on:keydown.escape.window="show = false"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
    x-cloak
>
    {{-- Gray Background --}}
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    {{-- Modal Content --}}
    <div x-show="show" class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidthClasses }} sm:mx-auto" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        {{ $slot }}
    </div>
</div> 