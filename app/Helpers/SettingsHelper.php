<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * Get the value of a setting.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting($key, $default = null)
    {
        return Cache::rememberForever('setting.'.$key, function () use ($key, $default) {
            return Setting::where('key', $key)->first()?->value ?? $default;
        });
    }
} 