<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Models\StreamLog;
use App\Services\StreamLoggingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Real-time debugging dashboard for streams
 */
class StreamDebugDashboard extends Component
{
    use WithPagination;

    public $selectedStream = null;
    public $selectedLevel = 'all';
    public $selectedCategory = 'all';
    public $autoRefresh = true;
    public $refreshInterval = 5; // seconds
    
    public $realTimeLogs = [];
    public $streamMetrics = [];
    
    protected $listeners = [
        'refreshDashboard' => '$refresh',
        'streamSelected' => 'selectStream'
    ];

    public function mount()
    {
        $this->loadRealTimeLogs();
        $this->loadStreamMetrics();
    }

    public function selectStream($streamId)
    {
        $this->selectedStream = $streamId;
        $this->loadRealTimeLogs();
        $this->resetPage();
    }

    public function setLevel($level)
    {
        $this->selectedLevel = $level;
        $this->resetPage();
    }

    public function setCategory($category)
    {
        $this->selectedCategory = $category;
        $this->resetPage();
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function refreshData()
    {
        $this->loadRealTimeLogs();
        $this->loadStreamMetrics();
        $this->dispatch('dataRefreshed');
    }

    public function clearLogs()
    {
        if ($this->selectedStream) {
            Redis::del("stream_logs:{$this->selectedStream}");
        } else {
            Redis::del("stream_logs:all");
        }
        
        $this->loadRealTimeLogs();
        session()->flash('success', 'Logs cleared successfully');
    }

    public function exportLogs()
    {
        $loggingService = app(StreamLoggingService::class);
        
        $filters = [
            'level' => $this->selectedLevel !== 'all' ? $this->selectedLevel : null,
            'category' => $this->selectedCategory !== 'all' ? $this->selectedCategory : null,
            'from_date' => now()->subDays(7),
            'limit' => 1000
        ];

        if ($this->selectedStream) {
            $result = $loggingService->getStreamLogs($this->selectedStream, $filters);
        } else {
            // Export all logs for user's streams
            $userStreams = Auth::user()->streamConfigurations()->pluck('id');
            $logs = StreamLog::whereIn('stream_id', $userStreams)
                ->when($filters['level'], fn($q) => $q->where('level', $filters['level']))
                ->when($filters['category'], fn($q) => $q->where('category', $filters['category']))
                ->where('created_at', '>=', $filters['from_date'])
                ->orderBy('created_at', 'desc')
                ->limit($filters['limit'])
                ->get();
            
            $result = ['success' => true, 'logs' => $logs->toArray()];
        }

        if ($result['success']) {
            $filename = 'stream_logs_' . now()->format('Y-m-d_H-i-s') . '.json';
            $content = json_encode($result['logs'], JSON_PRETTY_PRINT);
            
            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $filename, ['Content-Type' => 'application/json']);
        }

        session()->flash('error', 'Failed to export logs');
    }

    private function loadRealTimeLogs()
    {
        $loggingService = app(StreamLoggingService::class);
        $result = $loggingService->getRealTimeLogs($this->selectedStream, 50);
        
        if ($result['success']) {
            $this->realTimeLogs = $result['logs'];
        }
    }

    private function loadStreamMetrics()
    {
        try {
            $user = Auth::user();
            $streams = $user->streamConfigurations()
                ->whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
                ->get();

            $this->streamMetrics = [];
            
            foreach ($streams as $stream) {
                // Get recent error count
                $errorCount = StreamLog::forStream($stream->id)
                    ->errors()
                    ->recent(1)
                    ->count();

                // Get last activity
                $lastLog = StreamLog::forStream($stream->id)
                    ->latest()
                    ->first();

                $this->streamMetrics[$stream->id] = [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'status' => $stream->status,
                    'error_count' => $errorCount,
                    'last_activity' => $lastLog?->created_at?->diffForHumans(),
                    'health_score' => $this->calculateHealthScore($stream->id)
                ];
            }

        } catch (\Exception $e) {
            \Log::error("Failed to load stream metrics: {$e->getMessage()}");
        }
    }

    private function calculateHealthScore($streamId)
    {
        $recentLogs = StreamLog::forStream($streamId)
            ->recent(1)
            ->get();

        if ($recentLogs->isEmpty()) {
            return 50; // No data
        }

        $score = 100;
        $errorCount = $recentLogs->where('level', 'ERROR')->count();
        $warningCount = $recentLogs->where('level', 'WARNING')->count();

        $score -= ($errorCount * 20);
        $score -= ($warningCount * 5);

        return max(0, min(100, $score));
    }

    public function getLogsProperty()
    {
        $query = StreamLog::query();

        // Filter by stream
        if ($this->selectedStream) {
            $query->forStream($this->selectedStream);
        } else {
            // Only show logs for user's streams
            $userStreams = Auth::user()->streamConfigurations()->pluck('id');
            $query->whereIn('stream_id', $userStreams);
        }

        // Filter by level
        if ($this->selectedLevel !== 'all') {
            $query->level($this->selectedLevel);
        }

        // Filter by category
        if ($this->selectedCategory !== 'all') {
            $query->category($this->selectedCategory);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function getUserStreamsProperty()
    {
        return Auth::user()->streamConfigurations()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.stream-debug-dashboard', [
            'logs' => $this->logs,
            'userStreams' => $this->userStreams
        ]);
    }
}
