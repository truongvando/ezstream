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
    ];

    public function mount()
    {
        // Load existing settings or initialize with empty values
        $dbSettings = Setting::whereIn('key', $this->settingKeys)->pluck('value', 'key');
        foreach ($this->settingKeys as $key) {
            $this->settings[$key] = $dbSettings[$key] ?? '';
        }
    }

    public function save()
    {
        foreach ($this->settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        session()->flash('message', 'Settings saved successfully!');
    }

    public function render()
    {
        return view('livewire.admin.settings-manager')
            ->layout('layouts.admin');
    }
}
