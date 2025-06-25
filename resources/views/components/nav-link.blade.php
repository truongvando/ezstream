@props(['active'])

@php
$classes = ($active ?? false)
            ? 'px-4 py-2 rounded-lg bg-white/20 text-white font-semibold'
            : 'px-4 py-2 rounded-lg text-white/90 hover:text-white hover:bg-white/10 transition-all duration-200';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
