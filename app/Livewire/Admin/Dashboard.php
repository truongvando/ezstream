<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_users' => User::count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'active_streams' => StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])->count(),
            'error_streams' => StreamConfiguration::where('status', 'ERROR')->count(),
            'active_vps_servers' => VpsServer::where('status', 'ACTIVE')->count(),
            'total_revenue' => Transaction::where('status', 'COMPLETED')->sum('amount'),
            'pending_transactions' => Transaction::where('status', 'PENDING')->count(),
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

        $recentTransactions = Transaction::with(['user', 'servicePackage'])
            ->latest()
            ->take(5)
            ->get();
        
        $revenueData = Transaction::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total_revenue')
            )
            ->where('status', 'COMPLETED')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $chartData = [
            'labels' => $revenueData->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('d/m')),
            'datasets' => [
                [
                    'label' => 'Doanh thu',
                    'data' => $revenueData->pluck('total_revenue'),
                    'borderColor' => '#4F46E5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ]
            ],
        ];

        // Use real data
        return view('livewire.admin.dashboard', [
            'stats' => $stats,
            'recentStreams' => $recentStreams,
            'vpsStatuses' => $vpsStatuses,
            'recentTransactions' => $recentTransactions,
            'chartData' => $chartData,
        ])
            ->layout('layouts.sidebar');
    }
}
