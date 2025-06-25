<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Models\ServicePackage;
use App\Models\Subscription;
use App\Models\Transaction;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        //emporarily disable all complex queries to isolate the issue.
        
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
            
        $vpsStatuses = VpsServer::where('status', 'ACTIVE')->get()->map(function ($vps) {
            // In a real app, you'd get this from a monitoring service
            return [
                'id' => $vps->id,
                'name' => $vps->name,
                'ip_address' => $vps->ip_address,
                'cpu_usage' => rand(10, 80),
                'ram_usage' => rand(20, 90),
                'disk_usage' => rand(5, 95),
            ];
        });

        $recentTransactions = Transaction::with('user')
            ->latest()
            ->take(5)
            ->get();
        

        // Just render the view with dummy data.
        return view('livewire.admin.dashboard', [
            'stats' => ['total_users' => 0, 'active_streams' => 0, 'active_vps_servers' => 0, 'total_revenue' => 0],
            'recentStreams' => [],
            'vpsStatuses' => [],
            'recentTransactions' => [],
        ])
            ->layout('layouts.admin');
    }
}
