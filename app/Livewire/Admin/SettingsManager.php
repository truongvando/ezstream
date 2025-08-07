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
        'storage_mode' // stream_library only (streaming_method removed - always FFmpeg)
    ];

    public function mount()
    {
        // Load existing settings or initialize with empty values
        $dbSettings = Setting::whereIn('key', $this->settingKeys)->pluck('value', 'key');
        foreach ($this->settingKeys as $key) {
            if ($key === 'storage_mode') {
                $this->settings[$key] = 'stream_library'; // Only Stream Library supported
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

    // Backward compatibility method removed - only SRS is supported

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
