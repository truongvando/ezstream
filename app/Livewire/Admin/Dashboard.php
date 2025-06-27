<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Models\ServicePackage;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\SshService;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_users' => User::count(),
            'active_streams' => StreamConfiguration::where('status', 'ACTIVE')->count(),
            'active_vps_servers' => VpsServer::where('status', 'ACTIVE')->count(),
            'total_revenue' => Transaction::where('status', 'COMPLETED')->sum('amount'),
        ];

        $recentStreams = StreamConfiguration::with(['user', 'vpsServer'])
            ->latest()
            ->take(5)
            ->get();
            
        $vpsStatuses = VpsServer::where('status', 'ACTIVE')
            ->with('latestStat')
            ->get()
            ->map(function ($vps) {
                $latestStat = $vps->latestStat;
                
                $isOnline = $latestStat && $latestStat->created_at->gt(now()->subMinutes(5));

                return [
                    'id' => $vps->id,
                    'name' => $vps->name,
                    'ip_address' => $vps->ip_address,
                    'cpu_usage_percent' => $isOnline ? $latestStat->cpu_usage_percent : 0,
                    'ram_usage_percent' => $isOnline ? $latestStat->ram_usage_percent : 0,
                    'disk_usage_percent' => $isOnline ? $latestStat->disk_usage_percent : 0,
                    'status' => $isOnline ? 'online' : 'offline',
                    'last_updated' => $latestStat ? $latestStat->created_at->diffForHumans() : 'N/A',
                ];
            });

        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(5)
            ->get();
        

        // Use real data
        return view('livewire.admin.dashboard', [
            'stats' => $stats,
            'recentStreams' => $recentStreams,
            'vpsStatuses' => $vpsStatuses,
            'recentTransactions' => $recentTransactions,
        ])
            ->layout('layouts.sidebar')
            ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Admin Dashboard</h1>');
    }
}
