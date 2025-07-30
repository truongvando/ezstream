<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ApiKeyRotationService
{
    private $maxKeys = 3;

    public function __construct()
    {
        //
    }

    /**
     * Get current active YouTube API key
     */
    public function getCurrentYouTubeApiKey(): string
    {
        $currentIndex = $this->getCurrentKeyIndex();
        $key = env("YOUTUBE_API_KEY_{$currentIndex}");

        if (empty($key) || $key === 'your_second_youtube_api_key_here' || $key === 'your_third_youtube_api_key_here') {
            // Multiple fallback attempts
            $fallbackKey = env('YOUTUBE_API_KEY_1');

            if (empty($fallbackKey) || $fallbackKey === 'your_second_youtube_api_key_here') {
                $fallbackKey = env('YOUTUBE_API_KEY');
            }

            // Last resort: hardcoded for production (temporary fix)
            if (empty($fallbackKey) && app()->environment('production')) {
                $fallbackKey = 'AIzaSyAZsUCvHOGHYoUuVBMZsKJSXuDH5Czj1qw';
                Log::warning('Using hardcoded YouTube API key as last resort');
            }

            Log::info('Using fallback YouTube API key', [
                'requested_index' => $currentIndex,
                'fallback_used' => !empty($fallbackKey) ? 'yes' : 'no',
                'key_preview' => !empty($fallbackKey) ? substr($fallbackKey, 0, 10) . '...' : 'empty',
                'env_check' => [
                    'YOUTUBE_API_KEY_1' => env('YOUTUBE_API_KEY_1') ? 'found' : 'empty',
                    'YOUTUBE_API_KEY' => env('YOUTUBE_API_KEY') ? 'found' : 'empty'
                ]
            ]);

            return $fallbackKey ?: '';
        }

        return $key;
    }

    /**
     * Get current key index
     */
    public function getCurrentKeyIndex(): int
    {
        return (int) env('YOUTUBE_API_CURRENT_KEY_INDEX', 1);
    }

    /**
     * Rotate to next API key
     */
    public function rotateToNextKey(): array
    {
        $currentIndex = $this->getCurrentKeyIndex();
        $nextIndex = $currentIndex + 1;

        // Reset to 1 if exceeded max keys
        if ($nextIndex > $this->maxKeys) {
            $nextIndex = 1;
        }

        // Check if next key exists and is valid
        $nextKey = env("YOUTUBE_API_KEY_{$nextIndex}");
        if (empty($nextKey) || $nextKey === 'your_second_youtube_api_key_here' || $nextKey === 'your_third_youtube_api_key_here') {
            // Find next valid key
            for ($i = 1; $i <= $this->maxKeys; $i++) {
                if ($i === $currentIndex) continue;

                $testKey = env("YOUTUBE_API_KEY_{$i}");
                if (!empty($testKey) && $testKey !== 'your_second_youtube_api_key_here' && $testKey !== 'your_third_youtube_api_key_here') {
                    $nextIndex = $i;
                    break;
                }
            }
        }

        Log::info('YouTube API key rotated', [
            'from_index' => $currentIndex,
            'to_index' => $nextIndex,
            'new_key_preview' => substr($this->getCurrentYouTubeApiKey(), 0, 10) . '...'
        ]);

        return [
            'success' => true,
            'previous_index' => $currentIndex,
            'current_index' => $nextIndex,
            'message' => "API key đã được xoay từ key {$currentIndex} sang key {$nextIndex}"
        ];
    }


}
