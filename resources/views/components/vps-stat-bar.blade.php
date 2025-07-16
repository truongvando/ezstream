@props(['label', 'percentage', 'color' => 'gray'])

@php
    $bgColorClass = "bg-{$color}-500";
@endphp

<div>
    <div class="flex justify-between mb-1">
        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ number_format($percentage, 2) }}%</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
        <div class="{{ $bgColorClass }} h-2 rounded-full" style="width:{{ $percentage }}%"></div>
    </div>
</div> 