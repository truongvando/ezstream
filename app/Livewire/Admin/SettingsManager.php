<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

class SettingsManager extends Component
{
    public $settings = [];

    // Define all possible settings keys here
    protected $settingKeys = [
        'payment_api_endpoint',
        'payment_bank_id',
        'payment_account_no',
        'payment_account_name',
        'storage_mode', // server, cdn, hybrid
        'streaming_method', // srs, ffmpeg_encoding, ffmpeg_copy
        'ffmpeg_encoding_mode', // encoding, copy (backward compatibility)
    ];

    public function mount()
    {
        // Load existing settings or initialize with empty values
        $dbSettings = Setting::whereIn('key', $this->settingKeys)->pluck('value', 'key');
        foreach ($this->settingKeys as $key) {
            if ($key === 'storage_mode') {
                $this->settings[$key] = $dbSettings[$key] ?? 'server'; // Default to server for cost savings
            } elseif ($key === 'streaming_method') {
                $this->settings[$key] = $dbSettings[$key] ?? 'ffmpeg_copy'; // Default to FFmpeg copy mode
            } elseif ($key === 'ffmpeg_encoding_mode') {
                $this->settings[$key] = $dbSettings[$key] ?? 'copy'; // Default to copy with fast restart
            } else {
                $this->settings[$key] = $dbSettings[$key] ?? '';
            }
        }
    }

    public function save()
    {
        // Handle backward compatibility for streaming_method
        $this->handleStreamingMethodCompatibility();

        foreach ($this->settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Auto-refresh agent settings after save
        $this->refreshAgentSettings();

        session()->flash('message', 'Settings saved and agents refreshed successfully!');
    }

    /**
     * Handle backward compatibility between streaming_method and ffmpeg_encoding_mode
     */
    private function handleStreamingMethodCompatibility()
    {
        $streamingMethod = $this->settings['streaming_method'] ?? 'ffmpeg_copy';

        // Update ffmpeg_encoding_mode based on streaming_method for backward compatibility
        switch ($streamingMethod) {
            case 'srs':
                $this->settings['ffmpeg_encoding_mode'] = 'copy'; // SRS uses copy mode internally
                break;
            case 'ffmpeg_encoding':
                $this->settings['ffmpeg_encoding_mode'] = 'encoding';
                break;
            case 'ffmpeg_copy':
            default:
                $this->settings['ffmpeg_encoding_mode'] = 'copy';
                break;
        }
    }

    public function refreshAgentSettings()
    {
        try {
            \Artisan::call('agent:refresh-settings');
            $output = \Artisan::output();

            session()->flash('message', 'Agent settings refreshed successfully!');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to refresh agent settings: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.settings-manager')
            ->layout('layouts.sidebar')
        ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Cài đặt hệ thống</h1>');
    }
}
