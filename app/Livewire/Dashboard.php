<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Models\Subscription;
use Illuminate\Support\Number;

class Dashboard extends Component
{
    public $streamCount = 0;
    public $storageUsedFormatted = '0 B';
    public $storageLimit;
    public $activeSubscription = null;
    public $pendingSubscription;

    public function mount()
    {
        $user = Auth::user();
        
        // Safely get data with try-catch to avoid errors
        try {
            $this->streamCount = StreamConfiguration::where('user_id', $user->id)->count();
        } catch (\Exception $e) {
            $this->streamCount = 0;
        }

        try {
            $totalStorageUsed = UserFile::where('user_id', $user->id)->sum('size');
            $this->storageUsedFormatted = $this->formatBytes($totalStorageUsed);
        } catch (\Exception $e) {
            $this->storageUsedFormatted = '0 B';
        }

        try {
            $this->activeSubscription = $user->subscriptions()->where('status', 'ACTIVE')->with('servicePackage')->first();
        } catch (\Exception $e) {
            $this->activeSubscription = null;
        }

        if ($this->activeSubscription) {
            $this->storageLimit = $this->activeSubscription->servicePackage->storage_limit;
        } else {
            $this->storageLimit = 0;
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public function getStorageUsagePercentageProperty()
    {
        if ($this->storageLimit > 0) {
            return round(($this->totalStorageUsed / $this->storageLimit) * 100, 2);
        }
        return 0;
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'storageUsedFormatted' => $this->storageUsedFormatted,
            'storageLimitFormatted' => Number::fileSize($this->storageLimit, precision: 2),
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Dashboard</h1>');
    }
}
