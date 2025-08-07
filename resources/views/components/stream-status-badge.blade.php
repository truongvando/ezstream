@props(['status'])

@php
    $baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize';
    
    $colorClasses = match ($status) {
        'STREAMING' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'STARTING' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'STOPPED', 'INACTIVE' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'ERROR' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'STOPPING' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'waiting_for_processing' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        'ready' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',
        default => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
@endphp

<span class="{{ $baseClasses }} {{ $colorClasses }}">
    {{ $status }}
</span> 