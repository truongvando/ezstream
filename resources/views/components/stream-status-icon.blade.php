@props(['status', 'class' => 'h-6 w-6'])

@php
    $iconClass = '';
    $path = '';

    switch ($status) {
        case 'STREAMING':
            $iconClass = 'text-green-500';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.828a4 4 0 010-5.656m5.656 0a4 4 0 010 5.656" />';
            break;
        case 'STARTING':
            $iconClass = 'text-blue-500 animate-pulse';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
            break;
        case 'STOPPED':
        case 'INACTIVE':
            $iconClass = 'text-gray-400';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
            break;
        case 'ERROR':
            $iconClass = 'text-red-500';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
            break;
        case 'STOPPING':
            $iconClass = 'text-yellow-500 animate-pulse';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';
            break;
        default:
            $iconClass = 'text-purple-500';
            $path = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
            break;
    }
@endphp

<svg xmlns="http://www.w3.org/2000/svg" class="{{ $class }} {{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    {!! $path !!}
</svg> 