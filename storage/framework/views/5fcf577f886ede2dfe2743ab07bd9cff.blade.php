<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
@props(['status','class'])
<x-stream-status-icon :status="$status" :class="$class" >

{{ $slot ?? "" }}
</x-stream-status-icon>