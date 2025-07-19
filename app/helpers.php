<?php

if (!function_exists('setting')) {
    /**
     * Get a setting value from the database
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        try {
            $setting = \App\Models\Setting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error getting setting '{$key}': " . $e->getMessage());
            return $default;
        }
    }
}
