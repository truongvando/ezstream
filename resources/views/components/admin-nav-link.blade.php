@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block px-6 py-2 mt-2 text-sm text-gray-100 bg-gray-700 rounded'
            : 'block px-6 py-2 mt-2 text-sm text-gray-500 hover:bg-gray-700 hover:text-gray-100 rounded';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a> 