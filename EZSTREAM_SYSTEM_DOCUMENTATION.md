# 🎯 EZStream System Architecture & Python Migration Guide

## 📋 **System Overview**

EZStream là hệ thống streaming đa nền tảng cho phép users upload video và stream tới YouTube/các platform khác thông qua VPS servers.

### **Current Architecture:**
```
Laravel Frontend/Backend → Redis Pub/Sub → VPS Python Agents → FFmpeg → Nginx RTMP → YouTube
```

### **Target Architecture:**
```
React/Vue Frontend → Python FastAPI Backend → Redis Pub/Sub → VPS Python Agents → FFmpeg → Nginx RTMP → YouTube
```

---

## 🗄️ **Database Schema**

### **users**
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    package_id BIGINT,
    storage_used BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id)
);
```

### **packages**
```sql
CREATE TABLE packages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    max_storage BIGINT NOT NULL,
    max_concurrent_streams INT NOT NULL,
    max_video_quality ENUM('720p', '1080p', '4K') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    features JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **stream_configurations**
```sql
CREATE TABLE stream_configurations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('INACTIVE', 'STARTING', 'STREAMING', 'STOPPING', 'ERROR') DEFAULT 'INACTIVE',
    rtmp_url VARCHAR(500) NOT NULL,
    stream_key VARCHAR(255) NOT NULL,
    video_files JSON NOT NULL,
    video_source_path VARCHAR(500),
    loop BOOLEAN DEFAULT FALSE,
    playlist_order ENUM('sequential', 'random') DEFAULT 'sequential',
    enable_schedule BOOLEAN DEFAULT FALSE,
    schedule_start_time DATETIME NULL,
    schedule_end_time DATETIME NULL,
    keep_files_after_stop BOOLEAN DEFAULT FALSE,
    vps_server_id BIGINT NULL,
    last_started_at TIMESTAMP NULL,
    last_stopped_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vps_server_id) REFERENCES vps_servers(id) ON DELETE SET NULL
);
```

### **vps_servers**
```sql
CREATE TABLE vps_servers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    ssh_user VARCHAR(100) DEFAULT 'root',
    ssh_password VARCHAR(500) NOT NULL, -- Encrypted
    ssh_port INT DEFAULT 22,
    status ENUM('ACTIVE', 'INACTIVE', 'PROVISIONING', 'ERROR') DEFAULT 'INACTIVE',
    status_message TEXT,
    max_streams INT DEFAULT 4,
    current_streams INT DEFAULT 0,
    cpu_usage DECIMAL(5,2) DEFAULT 0,
    memory_usage DECIMAL(5,2) DEFAULT 0,
    disk_usage DECIMAL(5,2) DEFAULT 0,
    network_usage DECIMAL(10,2) DEFAULT 0,
    last_health_check TIMESTAMP NULL,
    webhook_configured BOOLEAN DEFAULT FALSE,
    multistream_support BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **video_files**
```sql
CREATE TABLE video_files (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    duration INT, -- seconds
    resolution VARCHAR(20), -- "1920x1080"
    format VARCHAR(10) DEFAULT 'mp4',
    disk VARCHAR(50) DEFAULT 'bunny_cdn',
    upload_status ENUM('uploading', 'completed', 'failed') DEFAULT 'uploading',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### **stream_progress**
```sql
CREATE TABLE stream_progress (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    stream_id BIGINT NOT NULL,
    stage VARCHAR(100) NOT NULL,
    progress INT DEFAULT 0, -- 0-100
    message VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stream_id) REFERENCES stream_configurations(id) ON DELETE CASCADE
);
```

---

## 🔄 **Current Laravel Backend Structure**

### **Directory Structure:**
```
app/
├── Console/Commands/
│   ├── ForceStopHangingStreams.php
│   ├── RedisHealthCheck.php
│   ├── StreamStatusListener.php
│   └── SubscribeToStreamStatusCommand.php
├── Http/Controllers/
│   ├── Api/VpsController.php
│   ├── FileUploadController.php
│   └── StreamController.php
├── Jobs/
│   ├── StartMultistreamJob.php
│   ├── StopMultistreamJob.php
│   ├── UpdateStreamStatusJob.php
│   └── ProvisionMultistreamVpsJob.php
├── Livewire/
│   ├── UserStreamManager.php
│   ├── AdminStreamManager.php
│   ├── FileManager.php
│   └── VpsServerManager.php
├── Models/
│   ├── User.php
│   ├── Package.php
│   ├── StreamConfiguration.php
│   ├── VpsServer.php
│   ├── VideoFile.php
│   └── StreamProgress.php
├── Services/
│   ├── SshService.php
│   ├── BunnyCdnService.php
│   ├── Vps/VpsConnection.php
│   ├── Vps/VpsMonitor.php
│   └── Stream/StreamManager.php
└── Exceptions/
    ├── Handler.php
    └── RedisConnectionHandler.php
```

---

## 🎮 **Core Laravel Components**

### **1. Stream Management (Livewire)**

#### **UserStreamManager.php**
```php
<?php
namespace App\Livewire;

use Livewire\Component;
use App\Models\StreamConfiguration;
use App\Jobs\StartMultistreamJob;
use App\Jobs\StopMultistreamJob;

class UserStreamManager extends Component
{
    public $streams;
    public $showCreateModal = false;

    // Form properties
    public $title = '';
    public $description = '';
    public $rtmp_url = '';
    public $stream_key = '';
    public $selected_files = [];
    public $loop = false;
    public $playlist_order = 'sequential';
    public $enable_schedule = false;
    public $schedule_start_time = '';
    public $schedule_end_time = '';
    public $keep_files_after_stop = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'rtmp_url' => 'required|url',
        'stream_key' => 'required|string',
        'selected_files' => 'required|array|min:1',
        'playlist_order' => 'in:sequential,random',
        'schedule_start_time' => 'nullable|date|after:now',
        'schedule_end_time' => 'nullable|date|after:schedule_start_time',
    ];

    public function mount()
    {
        $this->cleanupHangingStreams();
        $this->loadStreams();
    }

    public function loadStreams()
    {
        $this->streams = StreamConfiguration::where('user_id', auth()->id())
            ->with('vpsServer')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function store()
    {
        $this->validate();

        // Check user package limits
        $user = auth()->user();
        $activeStreams = StreamConfiguration::where('user_id', $user->id)
            ->whereIn('status', ['STARTING', 'STREAMING'])
            ->count();

        if ($activeStreams >= $user->package->max_concurrent_streams) {
            session()->flash('error', 'Exceeded maximum concurrent streams for your package.');
            return;
        }

        // Validate video files
        $videoFiles = $this->prepareVideoFiles();

        $stream = StreamConfiguration::create([
            'user_id' => $user->id,
            'title' => $this->title,
            'description' => $this->description,
            'rtmp_url' => $this->rtmp_url,
            'stream_key' => $this->stream_key,
            'video_files' => $videoFiles,
            'loop' => $this->loop,
            'playlist_order' => $this->playlist_order,
            'enable_schedule' => $this->enable_schedule,
            'schedule_start_time' => $this->schedule_start_time ?: null,
            'schedule_end_time' => $this->schedule_end_time ?: null,
            'keep_files_after_stop' => $this->keep_files_after_stop,
        ]);

        $this->showCreateModal = false;
        $this->loadStreams();
        session()->flash('message', 'Stream configuration created successfully.');
    }

    public function startStream($streamId)
    {
        $stream = StreamConfiguration::findOrFail($streamId);

        if ($stream->user_id !== auth()->id()) {
            return;
        }

        if ($stream->status !== 'INACTIVE') {
            session()->flash('error', 'Stream is not in inactive state.');
            return;
        }

        // Update status to STARTING
        $stream->update(['status' => 'STARTING']);

        // Dispatch job
        StartMultistreamJob::dispatch($stream);

        $this->loadStreams();
        session()->flash('message', 'Stream is starting...');
    }

    public function stopStream($streamId)
    {
        $stream = StreamConfiguration::findOrFail($streamId);

        if ($stream->user_id !== auth()->id()) {
            return;
        }

        if (!in_array($stream->status, ['STARTING', 'STREAMING'])) {
            session()->flash('error', 'Stream is not running.');
            return;
        }

        // Update status to STOPPING
        $stream->update(['status' => 'STOPPING']);

        // Dispatch stop job
        StopMultistreamJob::dispatch($stream);

        $this->loadStreams();
        session()->flash('message', 'Stream is stopping...');
    }

    public function deleteStream($streamId)
    {
        $stream = StreamConfiguration::findOrFail($streamId);

        if ($stream->user_id !== auth()->id()) {
            return;
        }

        if (in_array($stream->status, ['STARTING', 'STREAMING', 'STOPPING'])) {
            session()->flash('error', 'Cannot delete active stream.');
            return;
        }

        // Cleanup files if enabled
        if ($stream->keep_files_after_stop) {
            $this->cleanupStreamFiles($stream);
        }

        $stream->delete();
        $this->loadStreams();
        session()->flash('message', 'Stream deleted successfully.');
    }

    private function prepareVideoFiles()
    {
        $videoFiles = [];
        foreach ($this->selected_files as $fileId) {
            $file = VideoFile::where('id', $fileId)
                ->where('user_id', auth()->id())
                ->first();

            if ($file) {
                $videoFiles[] = [
                    'file_id' => $file->id,
                    'filename' => $file->filename,
                    'download_url' => "https://ezstream.b-cdn.net/{$file->file_path}",
                    'size' => $file->file_size,
                    'disk' => $file->disk,
                ];
            }
        }
        return $videoFiles;
    }

    private function cleanupHangingStreams()
    {
        $timeout = 300; // 5 minutes
        $hangingStreams = StreamConfiguration::where('status', 'STOPPING')
            ->where('user_id', auth()->id())
            ->where('updated_at', '<', now()->subSeconds($timeout))
            ->get();

        foreach ($hangingStreams as $stream) {
            $stream->update([
                'status' => 'INACTIVE',
                'last_stopped_at' => now(),
                'vps_server_id' => null,
            ]);
        }

        if ($hangingStreams->count() > 0) {
            session()->flash('message', "Auto-fixed {$hangingStreams->count()} hanging streams.");
        }
    }

    private function resetForm()
    {
        $this->title = '';
        $this->description = '';
        $this->rtmp_url = 'rtmp://a.rtmp.youtube.com/live2';
        $this->stream_key = '';
        $this->selected_files = [];
        $this->loop = false;
        $this->playlist_order = 'sequential';
        $this->enable_schedule = false;
        $this->schedule_start_time = '';
        $this->schedule_end_time = '';
        $this->keep_files_after_stop = false;
    }

    public function render()
    {
        return view('livewire.user-stream-manager');
    }
}
```

### **2. Background Jobs**

#### **StartMultistreamJob.php**
```php
<?php
namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Models\StreamProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class StartMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
    }

    public function handle(): void
    {
        Log::info("🚀 [StartMultistreamJob-Redis] Job started for stream #{$this->stream->id}");

        try {
            // Clear previous progress
            StreamProgress::where('stream_id', $this->stream->id)->delete();

            // Update initial progress
            StreamProgress::create([
                'stream_id' => $this->stream->id,
                'stage' => 'preparing',
                'progress' => 5,
                'message' => 'Đang gửi lệnh tới VPS...'
            ]);

            // Find optimal VPS
            $vps = $this->findOptimalVps();
            if (!$vps) {
                throw new \Exception('No available VPS servers');
            }

            // Update stream with VPS assignment
            $this->stream->update(['vps_server_id' => $vps->id]);
            $vps->increment('current_streams');

            // Prepare Redis command
            $redisCommand = [
                'command' => 'START_STREAM',
                'config' => [
                    'id' => $this->stream->id,
                    'video_files' => $this->stream->video_files,
                    'rtmp_url' => $this->stream->rtmp_url,
                    'push_urls' => null,
                    'loop' => $this->stream->loop,
                    'keep_files_after_stop' => $this->stream->keep_files_after_stop,
                ]
            ];

            // Publish to Redis with retry
            $channel = "vps-commands:{$vps->id}";
            $publishResult = $this->publishWithRetry($channel, $redisCommand);

            Log::info("✅ [Stream #{$this->stream->id}] Start command published", [
                'vps_id' => $vps->id,
                'publish_result' => $publishResult,
                'subscribers' => $publishResult > 0 ? 'YES' : 'NO'
            ]);

            // Update progress
            StreamProgress::create([
                'stream_id' => $this->stream->id,
                'stage' => 'command_sent',
                'progress' => 10,
                'message' => 'Lệnh đã gửi tới VPS, đang chờ agent xử lý...'
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] StartMultistreamJob failed", [
                'error' => $e->getMessage(),
            ]);

            $this->stream->update([
                'status' => 'ERROR',
                'vps_server_id' => null,
            ]);

            StreamProgress::create([
                'stream_id' => $this->stream->id,
                'stage' => 'error',
                'progress' => 0,
                'message' => "Job failed: " . $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function findOptimalVps(): ?VpsServer
    {
        $availableVps = VpsServer::where('status', 'ACTIVE')
            ->where('multistream_support', true)
            ->whereRaw('current_streams < max_streams')
            ->get();

        if ($availableVps->isEmpty()) {
            return null;
        }

        // VPS scoring algorithm
        $bestVps = null;
        $bestScore = 0;

        foreach ($availableVps as $vps) {
            $capacityScore = (($vps->max_streams - $vps->current_streams) / $vps->max_streams) * 40;
            $resourceScore = (100 - $vps->cpu_usage) * 0.3 + (100 - $vps->memory_usage) * 0.2;
            $connectionScore = 6.0; // Default connection score
            $performanceScore = 8.5; // Default performance score

            $totalScore = $capacityScore + $resourceScore + $connectionScore + $performanceScore;

            if ($totalScore > $bestScore) {
                $bestScore = $totalScore;
                $bestVps = $vps;
            }
        }

        Log::info("Selected optimal VPS", [
            'stream_id' => $this->stream->id,
            'vps_id' => $bestVps->id,
            'vps_name' => $bestVps->name,
            'score' => $bestScore,
            'current_streams' => $bestVps->current_streams,
            'max_streams' => $bestVps->max_streams
        ]);

        return $bestVps;
    }

    private function publishWithRetry(string $channel, array $command, int $maxRetries = 3): int
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $redisConfig = config('database.redis.default');
                $rawRedis = new PredisClient([
                    'scheme' => 'tcp',
                    'host' => $redisConfig['host'],
                    'port' => $redisConfig['port'],
                    'password' => $redisConfig['password'],
                    'database' => $redisConfig['database'],
                    'timeout' => 5.0,
                    'read_write_timeout' => 10.0,
                ]);

                $publishResult = $rawRedis->publish($channel, json_encode($command));

                Log::info("✅ [Stream #{$this->stream->id}] Redis publish successful on attempt {$attempt}");
                return $publishResult;

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("⚠️ [Stream #{$this->stream->id}] Redis publish attempt {$attempt} failed: {$e->getMessage()}");

                if ($attempt < $maxRetries) {
                    $waitTime = pow(2, $attempt - 1);
                    sleep($waitTime);
                }
            }
        }

        throw new \Exception("Redis publish failed after {$maxRetries} attempts. Last error: " . $lastException->getMessage());
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] StartMultistreamJob failed permanently", [
            'error' => $exception->getMessage(),
        ]);

        StreamProgress::create([
            'stream_id' => $this->stream->id,
            'stage' => 'error',
            'progress' => 0,
            'message' => "Job failed after retries: " . $exception->getMessage()
        ]);

        $this->stream->update([
            'status' => 'ERROR',
            'vps_server_id' => null,
        ]);
    }
}
```

#### **StopMultistreamJob.php**
```php
<?php
namespace App\Jobs;

use App\Models\StreamConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class StopMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
    }

    public function handle(): void
    {
        Log::info("🛑 [StopMultistreamJob-Redis] Job started for stream #{$this->stream->id}");

        try {
            $vpsId = $this->stream->vps_server_id;

            if (!$vpsId) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] No VPS ID assigned. Marking as INACTIVE directly.");
                $this->stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                    'vps_server_id' => null,
                ]);
                return;
            }

            // Create STOP command
            $redisCommand = [
                'command' => 'STOP_STREAM',
                'stream_id' => $this->stream->id,
            ];

            // Send command via Redis with retry
            $channel = "vps-commands:{$vpsId}";
            $publishResult = $this->publishWithRetry($channel, $redisCommand);

            Log::info("✅ [Stream #{$this->stream->id}] Stop command published to Redis channel '{$channel}'", [
                'publish_result' => $publishResult,
                'subscribers' => $publishResult > 0 ? 'YES' : 'NO'
            ]);

            // Update status immediately
            $this->stream->update([
                'status' => 'INACTIVE',
                'last_stopped_at' => now(),
                'vps_server_id' => null,
            ]);

            // Decrement VPS stream count
            if ($this->stream->vpsServer && $this->stream->vpsServer->current_streams > 0) {
                $this->stream->vpsServer->decrement('current_streams');
            }

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] StopMultistreamJob-Redis failed", [
                'error' => $e->getMessage(),
            ]);

            // Always update status to avoid hanging in STOPPING
            $this->stream->update([
                'status' => 'INACTIVE',
                'last_stopped_at' => now(),
                'vps_server_id' => null,
            ]);

            Log::warning("⚠️ [Stream #{$this->stream->id}] Stop job completed with errors but stream marked as INACTIVE");
        }
    }

    private function publishWithRetry(string $channel, array $command, int $maxRetries = 3): int
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $redisConfig = config('database.redis.default');
                $rawRedis = new PredisClient([
                    'scheme' => 'tcp',
                    'host' => $redisConfig['host'],
                    'port' => $redisConfig['port'],
                    'password' => $redisConfig['password'],
                    'database' => $redisConfig['database'],
                    'timeout' => 5.0,
                    'read_write_timeout' => 10.0,
                ]);

                $publishResult = $rawRedis->publish($channel, json_encode($command));

                Log::info("✅ [Stream #{$this->stream->id}] Redis publish successful on attempt {$attempt}");
                return $publishResult;

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("⚠️ [Stream #{$this->stream->id}] Redis publish attempt {$attempt} failed: {$e->getMessage()}");

                if ($attempt < $maxRetries) {
                    $waitTime = pow(2, $attempt - 1);
                    sleep($waitTime);
                }
            }
        }

        throw new \Exception("Redis publish failed after {$maxRetries} attempts. Last error: " . $lastException->getMessage());
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] StopMultistreamJob-Redis failed permanently", [
            'error' => $exception->getMessage(),
        ]);

        // Ensure stream doesn't hang in STOPPING status
        $this->stream->update([
            'status' => 'INACTIVE',
            'last_stopped_at' => now(),
            'vps_server_id' => null,
        ]);
    }
}
```

### **3. Redis Status Listener**

#### **StreamStatusListener.php**
```php
<?php
namespace App\Console\Commands;

use App\Jobs\UpdateStreamStatusJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class StreamStatusListener extends Command
{
    protected $signature = 'stream:listen
                            {--timeout=0 : Timeout in seconds (0 = no timeout)}
                            {--reconnect=true : Auto-reconnect on failure}';

    protected $description = 'Listen for stream status updates from VPS via Redis';

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $autoReconnect = $this->option('reconnect');

        $this->info("🎧 Starting Stream Status Listener...");
        $this->info("Timeout: " . ($timeout > 0 ? "{$timeout}s" : "No timeout"));
        $this->info("Auto-reconnect: " . ($autoReconnect ? "Yes" : "No"));

        $retryCount = 0;
        $maxRetries = 5;

        while ($retryCount < $maxRetries) {
            try {
                $this->listenToRedis($timeout);

                if (!$autoReconnect) {
                    break;
                }

                $retryCount++;
                $this->warn("⚠️ Connection lost. Retrying in 5 seconds... (Attempt {$retryCount}/{$maxRetries})");
                sleep(5);

            } catch (\Exception $e) {
                $this->error("❌ Redis listener error: {$e->getMessage()}");

                if (!$autoReconnect) {
                    return 1;
                }

                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    $this->error("💥 Max retries reached. Exiting.");
                    return 1;
                }

                $waitTime = min(60, pow(2, $retryCount));
                $this->warn("⚠️ Retrying in {$waitTime} seconds... (Attempt {$retryCount}/{$maxRetries})");
                sleep($waitTime);
            }
        }

        return 0;
    }

    private function listenToRedis(int $timeout = 0): void
    {
        $redisConfig = config('database.redis.default');
        $rawRedis = new PredisClient([
            'scheme' => 'tcp',
            'host' => $redisConfig['host'],
            'port' => $redisConfig['port'],
            'password' => $redisConfig['password'],
            'database' => $redisConfig['database'],
            'timeout' => 10.0,
            'read_write_timeout' => 0,
            'persistent' => false,
        ]);

        $this->info("✅ Connected to Redis: {$redisConfig['host']}:{$redisConfig['port']}");

        $channels = ['stream-status', 'stream-progress'];
        $this->info("📡 Subscribing to channels: " . implode(', ', $channels));

        $pubsub = $rawRedis->pubSubLoop();
        $pubsub->subscribe('stream-status', 'stream-progress');

        $startTime = time();
        $messageCount = 0;

        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                $messageCount++;
                $this->processMessage($message->channel, $message->payload);
            }

            if ($timeout > 0 && (time() - $startTime) > $timeout) {
                $this->info("⏰ Timeout reached after {$timeout}s. Processed {$messageCount} messages.");
                break;
            }

            if ($messageCount % 100 === 0 && $messageCount > 0) {
                $elapsed = time() - $startTime;
                $this->info("💓 Heartbeat: {$messageCount} messages processed in {$elapsed}s");
            }
        }

        $pubsub->unsubscribe();
        $this->info("🔌 Disconnected from Redis");
    }

    private function processMessage(string $channel, string $payload): void
    {
        $this->line("📨 [{$channel}] {$payload}");

        try {
            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("⚠️ Invalid JSON received: {$payload}");
                return;
            }

            // Dispatch job to update stream status
            if ($channel === 'stream-status') {
                UpdateStreamStatusJob::dispatch($data);
                $streamId = isset($data['stream_id']) ? $data['stream_id'] : 'unknown';
                $this->info("✅ UpdateStreamStatusJob dispatched for stream #{$streamId}");
            }

            // Log progress updates
            if ($channel === 'stream-progress') {
                $streamId = isset($data['stream_id']) ? $data['stream_id'] : 'unknown';
                $stage = isset($data['stage']) ? $data['stage'] : 'unknown';
                $message = isset($data['message']) ? $data['message'] : '';
                $this->info("📊 Progress - Stream #{$streamId}: {$stage} - {$message}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error processing message: {$e->getMessage()}");
            Log::error("Stream listener message processing error", [
                'channel' => $channel,
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

---

## 🔄 **Core Workflows**

### **1. Stream Start Workflow**

#### **Frontend Action (Livewire):**
1. User clicks "Bắt đầu Stream" button
2. `UserStreamManager::startStream()` validates stream state
3. Updates stream status to "STARTING" in database
4. Dispatches `StartMultistreamJob` to Redis queue
5. Returns success response to UI

#### **Backend Processing (Laravel Queue):**
1. `StartMultistreamJob` clears previous progress
2. Creates initial progress entry (5% - "Đang gửi lệnh tới VPS...")
3. Finds optimal VPS using scoring algorithm
4. Updates stream with VPS assignment
5. Creates Redis command with stream configuration
6. Publishes to Redis channel `vps-commands:{vps_id}` with retry mechanism
7. Updates progress (10% - "Lệnh đã gửi tới VPS...")

#### **VPS Processing (Python Agent - KEEP AS IS):**
1. Agent subscribes to Redis channel `vps-commands:{vps_id}`
2. Receives START_STREAM command
3. Downloads video files from BunnyCDN to `/tmp/ezstream_downloads/{stream_id}/`
4. Publishes progress updates to `stream-status` channel
5. Builds FFmpeg command: `ffmpeg -re -stream_loop -1 -i {video_file} -c copy -f flv rtmp://127.0.0.1:1935/live/{stream_id}`
6. Starts FFmpeg process
7. Publishes "ffmpeg_started" progress (90%)

#### **Nginx RTMP (VPS - NEEDS FIX):**
```nginx
# Current config (missing push directive)
rtmp {
    server {
        listen 1935;
        application live {
            live on;
            allow publish 127.0.0.1;
            # MISSING: push rtmp://a.rtmp.youtube.com/live2/{stream_key};
        }
    }
}
```

#### **Progress Tracking (Laravel - NEEDS LISTENER):**
1. `StreamStatusListener` subscribes to Redis `stream-status` channel
2. Receives progress updates from VPS
3. Updates `stream_progress` table
4. Dispatches `UpdateStreamStatusJob`
5. Updates stream status in database

### **2. Stream Stop Workflow**

#### **Frontend Action:**
1. User clicks "Dừng Stream" button
2. Updates stream status to "STOPPING"
3. Dispatches `StopMultistreamJob`

#### **Backend Processing:**
1. Creates STOP_STREAM command
2. Publishes to Redis `vps-commands:{vps_id}`
3. Updates stream status to "INACTIVE" immediately
4. Clears `vps_server_id` and decrements VPS stream count

#### **VPS Processing:**
1. Receives STOP_STREAM command
2. Kills FFmpeg process
3. Cleans up downloaded files (if `keep_files_after_stop = false`)
4. Publishes status update

---

## 🔧 **VPS Management System**

### **VPS Provisioning Process:**
```php
// ProvisionMultistreamVpsJob.php workflow
1. SSH connect to VPS server
2. Update system packages: apt update && apt upgrade -y
3. Install required packages:
   - python3, python3-pip, redis-tools
   - ffmpeg, nginx, libnginx-mod-rtmp
4. Upload agent.py script to /root/
5. Install Python dependencies: pip3 install redis requests
6. Configure nginx.conf with RTMP module
7. Create systemd service for agent.py
8. Start and enable agent service
9. Configure Redis connection settings
10. Update VPS status to "ACTIVE" in database
```

### **VPS Health Monitoring:**
```php
// VpsMonitor.php - runs every 5 minutes
1. SSH to each ACTIVE VPS server
2. Check system resources:
   - CPU usage: cat /proc/loadavg
   - Memory usage: free -m
   - Disk usage: df -h /tmp
3. Verify agent.py is running: ps aux | grep agent.py
4. Test Redis connectivity: redis-cli ping
5. Update vps_servers table with metrics
6. Mark unhealthy VPS as "INACTIVE"
```

### **VPS Selection Algorithm:**
```php
// VpsSelector.php - findOptimalVps()
Scoring factors:
- Capacity Score: ((max_streams - current_streams) / max_streams) * 40
- Resource Score: (100 - cpu_usage) * 0.3 + (100 - memory_usage) * 0.2
- Connection Score: ping_latency_score * 6
- Performance Score: historical_success_rate * 10

Total Score = Capacity + Resource + Connection + Performance
Select VPS with highest total score
```

---

## 📁 **File Management System**

### **Upload Process:**
```php
// FileUploadController.php workflow
1. Validate file constraints:
   - Format: MP4 only
   - Size: Admin unlimited, users max 10GB per file
   - Resolution: Must match user package limits (720p/1080p/4K)
2. Check user storage quota against package limits
3. Generate unique filename: {timestamp}_{user_id}_{original_name}
4. Upload to BunnyCDN via PUT request
5. Extract video metadata using FFprobe:
   - Duration, resolution, bitrate, codec
6. Validate resolution against user package
7. Save to video_files table
8. Update user storage_used counter
```

### **File Validation Rules:**
```php
- Format: MP4 only (validated via MIME type and extension)
- Size Limits:
  - Admin: Unlimited
  - Regular users: 10GB per file
- Resolution Limits (by package):
  - Basic: Up to 1080p
  - Pro: Up to 4K
  - Premium: Unlimited
- Storage Quotas:
  - Basic: 50GB total
  - Pro: 200GB total
  - Premium: 500GB total
- Validation: Before upload starts (not after completion)
```

### **BunnyCDN Integration:**
```php
// BunnyCdnService.php
Base URL: https://storage.bunnycdn.com/{storage_zone}/
CDN URL: https://ezstream.b-cdn.net/{file_path}
Authentication: AccessKey header
Operations:
- Upload: PUT /{storage_zone}/{path}
- Delete: DELETE /{storage_zone}/{path}
- List: GET /{storage_zone}/{path}/
```

---

## 🔄 **Redis Communication Architecture**

### **Channels:**

#### **Laravel → VPS:**
```
Channel: "vps-commands:{vps_id}"
Message Format:
{
  "command": "START_STREAM|STOP_STREAM|PING",
  "config": {
    "id": stream_id,
    "video_files": [file_objects],
    "rtmp_url": "rtmp://a.rtmp.youtube.com/live2",
    "push_urls": null,
    "loop": boolean,
    "keep_files_after_stop": boolean
  },
  "timestamp": unix_timestamp
}
```

#### **VPS → Laravel:**
```
Channel: "stream-status"
Message Format:
{
  "stream_id": int,
  "status": "STREAMING|STOPPED|ERROR",
  "message": "descriptive_message",
  "vps_id": int,
  "timestamp": unix_timestamp,
  "ffmpeg_pid": int (optional),
  "output_url": "rtmp_url" (optional)
}

Channel: "stream-progress"
Message Format:
{
  "stream_id": int,
  "stage": "downloading|file_ready|ffmpeg_started|streaming",
  "progress": 0-100,
  "message": "progress_description",
  "timestamp": unix_timestamp
}
```

### **Redis Configuration:**
```php
// config/database.php
'redis' => [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'timeout' => 5.0,
        'read_write_timeout' => 10.0,
    ],
    'queue' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0, // Same as default
        'timeout' => 5.0,
        'read_write_timeout' => 30.0,
    ]
]
```

---

## 🎮 **User Interface Components**

### **Livewire Components:**

#### **AdminStreamManager.php:**
```php
Properties:
- $streams (all streams in system)
- $selectedVps (for VPS testing mode)
- $showCreateModal (modal state)
- Form fields for stream creation

Methods:
- mount(): Auto-cleanup hanging streams + VPS testing mode
- create(): Show admin stream creation modal
- store(): Create stream with admin privileges
- startStream($streamId): Force start any stream
- stopStream($streamId): Force stop any stream
- forceStop($streamId): Force stop hanging streams
- deleteStream($streamId): Delete any stream
- viewLogs($streamId): Show stream logs and progress
```

#### **FileManager.php:**
```php
Properties:
- $files (user's video files)
- $uploading (upload state tracking)
- $uploadProgress (real-time progress)
- $selectedFiles (for bulk operations)

Methods:
- mount(): Load user files with pagination
- upload(): Handle file upload with validation
- delete($fileId): Delete file from CDN and database
- validateFile(): Check size/format/resolution/quota
- bulkDelete(): Delete multiple files
- refreshFiles(): Reload file list
```

#### **VpsServerManager.php:**
```php
Properties:
- $servers (VPS server list)
- $showCreateModal (modal state)
- $showProvisionModal (provisioning modal)
- Form fields for VPS creation

Methods:
- mount(): Load VPS servers with health status
- create(): Show VPS creation modal
- store(): Create new VPS server
- provision($vpsId): Start VPS provisioning job
- testConnection($vpsId): Test SSH connectivity
- healthCheck($vpsId): Manual health check
- delete($vpsId): Remove VPS server
```

### **Frontend JavaScript:**
```javascript
// Real-time progress polling
function pollStreamProgress(streamId) {
    setInterval(() => {
        fetch(`/api/stream-progress/${streamId}`)
            .then(response => response.json())
            .then(data => {
                updateProgressBar(data.progress);
                updateStatusMessage(data.message);
                updateStreamStatus(data.status);
            })
            .catch(error => console.error('Progress poll error:', error));
    }, 2000);
}

// File upload with progress tracking
function uploadFileWithProgress(file, progressCallback) {
    const formData = new FormData();
    formData.append('video', file);

    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressCallback(percentComplete);
        }
    });

    xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            handleUploadSuccess(response);
        } else {
            handleUploadError(xhr.responseText);
        }
    });

    xhr.open('POST', '/upload');
    xhr.send(formData);
}

// Stream status updates
function updateStreamStatus(streamId, status) {
    const statusElement = document.getElementById(`stream-${streamId}-status`);
    const buttonElement = document.getElementById(`stream-${streamId}-button`);

    statusElement.textContent = status;
    statusElement.className = `status-${status.toLowerCase()}`;

    // Update button based on status
    switch(status) {
        case 'INACTIVE':
            buttonElement.textContent = 'Bắt đầu Stream';
            buttonElement.disabled = false;
            break;
        case 'STARTING':
            buttonElement.textContent = 'Đang khởi động...';
            buttonElement.disabled = true;
            break;
        case 'STREAMING':
            buttonElement.textContent = 'Dừng Stream';
            buttonElement.disabled = false;
            break;
        case 'STOPPING':
            buttonElement.textContent = 'Đang dừng...';
            buttonElement.disabled = true;
            break;
    }
}
```

---

## ⚙️ **Configuration & Settings**

### **Environment Variables:**
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ezstream
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# BunnyCDN
BUNNY_CDN_API_KEY=your_api_key
BUNNY_CDN_STORAGE_ZONE=ezstream
BUNNY_CDN_BASE_URL=https://ezstream.b-cdn.net

# Queue System
QUEUE_CONNECTION=redis

# Session
SESSION_LIFETIME=43200 # 30 days

# Application
APP_URL=http://localhost # For webhooks (currently not used)
APP_DEBUG=true
APP_ENV=local
```

### **Package Configuration:**
```php
// Default packages in database seeder
[
    'Basic' => [
        'max_storage' => 50 * 1024 * 1024 * 1024, // 50GB
        'max_concurrent_streams' => 2,
        'max_video_quality' => '1080p',
        'price' => 29.99
    ],
    'Pro' => [
        'max_storage' => 200 * 1024 * 1024 * 1024, // 200GB
        'max_concurrent_streams' => 5,
        'max_video_quality' => '4K',
        'price' => 79.99
    ],
    'Premium' => [
        'max_storage' => 500 * 1024 * 1024 * 1024, // 500GB
        'max_concurrent_streams' => 10,
        'max_video_quality' => '4K',
        'price' => 149.99
    ]
]
```

---

## 🔍 **Monitoring & Debugging**

### **Logging System:**
```php
// All logs go to storage/logs/laravel.log
Log::info("Stream started", ['stream_id' => $id, 'user_id' => $userId]);
Log::error("VPS connection failed", ['vps_id' => $id, 'error' => $message]);
Log::debug("Progress update", ['stream_id' => $id, 'progress' => $percent]);
Log::warning("Redis connection retry", ['attempt' => $attempt, 'error' => $error]);
```

### **Health Check Commands:**
```bash
# Check Redis connections and fix issues
php artisan redis:health-check --connection=queue --fix

# Force stop hanging streams (timeout after 5 minutes)
php artisan streams:force-stop-hanging --timeout=300

# Monitor stream status (run continuously)
php artisan stream:listen --timeout=30

# Check scheduled tasks
php artisan schedule:list

# Monitor queue workers
php artisan queue:monitor redis --max-time=60

# Check VPS server health
php artisan vps:health-check --all
```

### **Debug Tools Created:**
```php
// Debug scripts for troubleshooting
- debug_vps_agent.php - Test VPS agent connectivity and commands
- debug_redis_subscribe.php - Test Redis pub/sub functionality
- debug_vps_ffmpeg.php - Check FFmpeg processes on VPS
- check_nginx_push.php - Verify nginx RTMP configuration
- check_agent_code.php - Validate VPS agent.py code
```

---

## 🚨 **Known Issues & Solutions**

### **1. Redis Connection Errors:**
```
Problem: errno=10053 connection aborted during high load
Solution:
- Added retry mechanism with exponential backoff
- Improved timeout settings (5s connect, 10s read/write)
- Error throttling to prevent log spam
- Connection pooling for better resource management
```

### **2. Streams Hanging in STOPPING Status:**
```
Problem: StopMultistreamJob fails but stream status not updated
Solution:
- Always update status to INACTIVE even on Redis failure
- Auto-cleanup hanging streams on page load (5 minute timeout)
- Scheduled cleanup task every 5 minutes
- Force stop command for admin users
```

### **3. Missing Progress Updates:**
```
Problem: VPS sends progress but Laravel doesn't receive
Solution:
- Must run StreamStatusListener continuously: php artisan stream:listen
- Added auto-reconnect with retry logic
- Heartbeat monitoring every 100 messages
- Timeout handling for long-running listeners
```

### **4. Nginx RTMP Push Missing:**
```
Problem: FFmpeg streams to nginx but doesn't forward to YouTube
Solution:
- Add push directive to nginx.conf application live section:
  push rtmp://a.rtmp.youtube.com/live2/{stream_key};
- Configure push_reconnect and push_timeout settings
- Verify nginx-rtmp-module is loaded properly
```

### **5. File Upload Validation Issues:**
```
Problem: Large files fail validation or exceed quotas
Solution:
- Validate before upload starts (not after completion)
- Check user package limits and storage quotas
- Implement chunked upload for large files
- Better error messages for quota exceeded
```

---

## 🔄 **Queue System**

### **Jobs Configuration:**

#### **StartMultistreamJob:**
```php
- Queue: redis
- Timeout: 300 seconds (5 minutes)
- Tries: 3 attempts
- Purpose: Send start command to VPS via Redis
- Retry Logic: Exponential backoff on Redis connection failure
- Failure Handling: Mark stream as ERROR status
```

#### **StopMultistreamJob:**
```php
- Queue: redis
- Timeout: 60 seconds
- Tries: 3 attempts
- Purpose: Send stop command to VPS via Redis
- Always updates stream status to INACTIVE even on failure
- Decrements VPS current_streams counter
```

#### **UpdateStreamStatusJob:**
```php
- Queue: redis
- Purpose: Update stream status from VPS progress reports
- Triggered by: StreamStatusListener receiving Redis messages
- Updates: stream_configurations and stream_progress tables
```

#### **ProvisionMultistreamVpsJob:**
```php
- Queue: redis
- Timeout: 1800 seconds (30 minutes)
- Purpose: Setup new VPS server with required software
- Steps: SSH setup, package installation, agent deployment
- Failure Handling: Mark VPS as ERROR status with details
```

### **Queue Workers:**
```bash
# Standard worker
php artisan queue:work redis --timeout=60 --sleep=3 --tries=3

# Worker with enhanced retry logic (for Redis issues)
php artisan queue:work-retry redis --max-redis-retries=5 --backoff=1,3,5

# Supervisor configuration for production
[program:ezstream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/ezstream/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/ezstream/storage/logs/worker.log
```

---

## 📅 **Scheduled Tasks**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Check for scheduled streams every minute
    $schedule->command('streams:check-scheduled')
             ->everyMinute()
             ->withoutOverlapping()
             ->runInBackground();

    // Force stop hanging streams every 5 minutes
    $schedule->command('streams:force-stop-hanging --timeout=300')
             ->everyFiveMinutes()
             ->withoutOverlapping()
             ->runInBackground();

    // Redis health check every 10 minutes
    $schedule->command('redis:health-check --connection=queue --fix')
             ->everyTenMinutes()
             ->withoutOverlapping()
             ->runInBackground();

    // VPS health monitoring every 5 minutes
    $schedule->command('vps:health-check --all')
             ->everyFiveMinutes()
             ->withoutOverlapping()
             ->runInBackground();

    // Cleanup old progress records daily
    $schedule->command('streams:cleanup-progress --days=7')
             ->daily()
             ->at('02:00');

    // Generate usage reports weekly
    $schedule->command('reports:generate-usage')
             ->weekly()
             ->sundays()
             ->at('03:00');
}
```

---

## 🔐 **Security & Authentication**

### **User Authentication:**
```php
// Laravel Breeze implementation
- Session-based authentication with 30-day lifetime
- Password hashing with bcrypt (cost factor 12)
- Email verification optional
- Remember me functionality
- Password reset via email tokens
- CSRF protection on all forms
- Rate limiting on login attempts
```

### **VPS Security:**
```php
// SSH Security
- Key-based authentication preferred over passwords
- Passwords stored encrypted in database
- SSH connections timeout after 30 seconds
- Only root user access for provisioning
- Firewall rules: Only allow SSH (22), RTMP (1935), HTTP (8080)

// Agent Security
- VPS agents only accept commands from localhost Redis
- No external API endpoints exposed
- File downloads only from trusted CDN domains
- Process isolation for FFmpeg streams
```

### **API Security:**
```php
// File Upload Security
- MIME type validation (video/mp4 only)
- File extension whitelist (.mp4)
- File size limits based on user packages
- Virus scanning integration (optional)
- CDN-based storage (not local filesystem)

// Package-based Restrictions
- Storage quota enforcement
- Concurrent stream limits
- Video quality restrictions
- Feature access control
```

---

## 🎯 **VPS Agent Code (Python - KEEP AS IS)**

### **🔥 UPDATED: Dynamic Nginx Apps with Stream Keys**

The VPS agent has been enhanced with dynamic nginx application creation for each stream with proper YouTube stream keys.

### **Key Features Added:**
- **Dynamic nginx apps**: Creates `/etc/nginx/rtmp-apps/stream_{stream_id}.conf` for each stream
- **Stream key integration**: Uses actual YouTube stream key from config
- **Auto cleanup**: Removes nginx app config when stream stops
- **Hot reload**: Automatically reloads nginx when apps are added/removed

### **agent.py Structure:**
```python
#!/usr/bin/env python3
import redis
import json
import subprocess
import os
import requests
import threading
import time
import signal
import sys
from datetime import datetime

class StreamAgent:
    def __init__(self):
        self.redis_client = redis.Redis(host='localhost', port=6379, db=0)
        self.vps_id = self.get_vps_id()
        self.active_streams = {}
        self.running = True

    def get_vps_id(self):
        # Get VPS ID from config file or environment
        try:
            with open('/etc/ezstream/vps_id', 'r') as f:
                return int(f.read().strip())
        except:
            return 1  # Default VPS ID

    def listen_for_commands(self):
        pubsub = self.redis_client.pubsub()
        channel = f"vps-commands:{self.vps_id}"
        pubsub.subscribe(channel)

        print(f"🎧 Listening for commands on channel: {channel}")

        for message in pubsub.listen():
            if message['type'] == 'message':
                try:
                    command_data = json.loads(message['data'])
                    self.process_command(command_data)
                except Exception as e:
                    print(f"❌ Error processing command: {e}")

    def process_command(self, command_data):
        command = command_data.get('command')

        if command == 'START_STREAM':
            self.start_stream(command_data['config'])
        elif command == 'STOP_STREAM':
            self.stop_stream(command_data['stream_id'])
        elif command == 'PING':
            self.send_pong()

    def start_stream(self, config):
        stream_id = config['id']
        video_files = config['video_files']
        rtmp_url = config['rtmp_url']
        loop = config.get('loop', False)

        print(f"🚀 Starting stream #{stream_id}")

        # Download video files
        download_dir = f"/tmp/ezstream_downloads/{stream_id}"
        os.makedirs(download_dir, exist_ok=True)

        local_files = []
        for i, file_info in enumerate(video_files):
            self.publish_progress(stream_id, 'downloading', 20 + (i * 30),
                                f"Downloading {file_info['filename']}...")

            local_path = self.download_file(file_info['download_url'],
                                          download_dir, file_info['filename'])
            if local_path:
                local_files.append(local_path)

        if not local_files:
            self.publish_status(stream_id, 'ERROR', 'No files downloaded successfully')
            return

        # Start FFmpeg streaming
        self.publish_progress(stream_id, 'ffmpeg_starting', 80, 'Starting FFmpeg...')

        ffmpeg_process = self.start_ffmpeg(stream_id, local_files, loop)

        if ffmpeg_process:
            self.active_streams[stream_id] = {
                'process': ffmpeg_process,
                'files': local_files,
                'download_dir': download_dir,
                'config': config
            }

            self.publish_progress(stream_id, 'streaming', 100, 'Stream is live!')
            self.publish_status(stream_id, 'STREAMING', 'Stream started successfully')
        else:
            self.publish_status(stream_id, 'ERROR', 'Failed to start FFmpeg')

    def start_ffmpeg(self, stream_id, video_files, loop=False):
        # Build FFmpeg command
        input_args = []

        if loop:
            input_args.extend(['-stream_loop', '-1'])

        # Create playlist file for multiple videos
        if len(video_files) > 1:
            playlist_path = f"/tmp/ezstream_downloads/{stream_id}/playlist.txt"
            with open(playlist_path, 'w') as f:
                for video_file in video_files:
                    f.write(f"file '{video_file}'\n")
            input_args.extend(['-f', 'concat', '-safe', '0', '-i', playlist_path])
        else:
            input_args.extend(['-i', video_files[0]])

        cmd = [
            'ffmpeg',
            '-re',
            *input_args,
            '-c', 'copy',
            '-f', 'flv',
            f'rtmp://127.0.0.1:1935/live/{stream_id}'
        ]

        try:
            process = subprocess.Popen(cmd, stdout=subprocess.PIPE,
                                     stderr=subprocess.PIPE)
            print(f"✅ FFmpeg started for stream #{stream_id}, PID: {process.pid}")
            return process
        except Exception as e:
            print(f"❌ Failed to start FFmpeg: {e}")
            return None

    def stop_stream(self, stream_id):
        if stream_id in self.active_streams:
            stream_info = self.active_streams[stream_id]
            process = stream_info['process']

            # Kill FFmpeg process
            try:
                process.terminate()
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill()

            # Cleanup files if configured
            config = stream_info['config']
            if not config.get('keep_files_after_stop', False):
                self.cleanup_files(stream_info['download_dir'])

            del self.active_streams[stream_id]

            self.publish_status(stream_id, 'STOPPED', 'Stream stopped successfully')
            print(f"🛑 Stream #{stream_id} stopped")

    def download_file(self, url, download_dir, filename):
        try:
            local_path = os.path.join(download_dir, filename)

            response = requests.get(url, stream=True)
            response.raise_for_status()

            with open(local_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)

            print(f"✅ Downloaded: {filename}")
            return local_path

        except Exception as e:
            print(f"❌ Download failed for {filename}: {e}")
            return None

    def cleanup_files(self, download_dir):
        try:
            import shutil
            shutil.rmtree(download_dir)
            print(f"🧹 Cleaned up: {download_dir}")
        except Exception as e:
            print(f"⚠️ Cleanup failed: {e}")

    def publish_progress(self, stream_id, stage, progress, message):
        data = {
            'stream_id': stream_id,
            'stage': stage,
            'progress': progress,
            'message': message,
            'vps_id': self.vps_id,
            'timestamp': int(time.time())
        }
        self.redis_client.publish('stream-progress', json.dumps(data))

    def publish_status(self, stream_id, status, message):
        data = {
            'stream_id': stream_id,
            'status': status,
            'message': message,
            'vps_id': self.vps_id,
            'timestamp': int(time.time())
        }
        self.redis_client.publish('stream-status', json.dumps(data))

    def send_pong(self):
        data = {
            'command': 'PONG',
            'vps_id': self.vps_id,
            'timestamp': int(time.time()),
            'active_streams': len(self.active_streams)
        }
        self.redis_client.publish('vps-status', json.dumps(data))

if __name__ == "__main__":
    agent = StreamAgent()

    def signal_handler(sig, frame):
        print("\n🛑 Shutting down agent...")
        agent.running = False
        sys.exit(0)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    try:
        agent.listen_for_commands()
    except KeyboardInterrupt:
        print("\n🛑 Agent stopped by user")
    except Exception as e:
        print(f"💥 Agent crashed: {e}")
```

### **🔥 NEW: Dynamic Nginx App Creation**

#### **create_nginx_app() method:**
```python
def create_nginx_app(self, stream_id, stream_key):
    """
    Tạo file cấu hình app nginx động cho từng stream
    """
    try:
        app_dir = '/etc/nginx/rtmp-apps'
        os.makedirs(app_dir, exist_ok=True)
        conf_path = f'{app_dir}/stream_{stream_id}.conf'
        with open(conf_path, 'w') as f:
            f.write(f"""
application stream_{stream_id} {{
    live on;
    record off;
    allow play all;
    push rtmp://a.rtmp.youtube.com/live2/{stream_key};
}}
""")
        logging.info(f"Created nginx app conf: {conf_path} with stream_key: {stream_key}")
    except Exception as e:
        logging.error(f"Failed to create nginx app conf for stream {stream_id}: {e}")

def remove_nginx_app(self, stream_id):
    """
    Xóa file cấu hình app nginx động khi stop stream
    """
    try:
        conf_path = f'/etc/nginx/rtmp-apps/stream_{stream_id}.conf'
        if os.path.exists(conf_path):
            os.remove(conf_path)
            logging.info(f"Removed nginx app conf: {conf_path}")
    except Exception as e:
        logging.error(f"Failed to remove nginx app conf for stream {stream_id}: {e}")

def reload_nginx(self):
    try:
        result = os.system('nginx -s reload')
        if result == 0:
            logging.info("Reloaded nginx successfully.")
        else:
            logging.error(f"Failed to reload nginx, exit code: {result}")
    except Exception as e:
        logging.error(f"Exception when reloading nginx: {e}")
```

### **🔧 Updated Workflow:**

#### **START_STREAM Command Processing:**
```python
def handle_command(self, command_data):
    command = command_data.get('command')
    config = command_data.get('config', {})
    stream_id = config.get('id') if config else command_data.get('stream_id')

    if command == 'START_STREAM':
        # 1. Save stream config
        self.stream_configs[stream_id] = config

        # 2. Extract stream key from config
        stream_key = config.get('stream_key') or config.get('rtmp_key') or config.get('id')
        if not stream_key:
            logging.error(f"No stream_key provided for stream {stream_id}")
            self.publish_stream_status(stream_id, 'ERROR', 'No stream_key provided')
            return

        # 3. Create dynamic nginx app with stream key
        self.create_nginx_app(stream_id, stream_key)

        # 4. Reload nginx to apply new config
        self.reload_nginx()

        # 5. Start FFmpeg streaming to dynamic app
        self.start_stream(config)
```

#### **FFmpeg Command Updated:**
```python
# OLD: Fixed RTMP endpoint
ffmpeg_cmd = [
    'ffmpeg', '-re', '-stream_loop', '-1',
    '-i', local_video_path,
    '-c', 'copy',
    '-f', 'flv',
    f"rtmp://127.0.0.1:1935/live/{stream_id}"  # ❌ Fixed app
]

# NEW: Dynamic app endpoint
ffmpeg_cmd = [
    'ffmpeg', '-re', '-stream_loop', '-1',
    '-i', local_video_path,
    '-c', 'copy',
    '-f', 'flv',
    f"rtmp://127.0.0.1:1935/stream_{stream_id}/stream_{stream_id}"  # ✅ Dynamic app
]
```

#### **STOP_STREAM Command Processing:**
```python
def stop_stream(self, stream_id):
    # 1. Stop FFmpeg process
    if stream_id in self.streams:
        process = self.streams[stream_id]
        if process.poll() is None:
            process.terminate()
            process.wait(timeout=5)
        del self.streams[stream_id]

    # 2. Remove stream config
    if stream_id in self.stream_configs:
        del self.stream_configs[stream_id]

    # 3. Cleanup downloaded files
    self.cleanup_stream_files(stream_id)

    # 4. Remove dynamic nginx app
    self.remove_nginx_app(stream_id)

    # 5. Reload nginx to apply changes
    self.reload_nginx()
```

### **📁 Nginx Configuration Structure:**

#### **Main nginx.conf:**
```nginx
# /etc/nginx/nginx.conf
rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        allow publish 127.0.0.1;
        deny publish all;

        # Include all dynamic apps
        include /etc/nginx/rtmp-apps/*.conf;
    }
}
```

#### **Dynamic App Files:**
```nginx
# /etc/nginx/rtmp-apps/stream_47.conf
application stream_47 {
    live on;
    record off;
    allow play all;
    push rtmp://a.rtmp.youtube.com/live2/7cs4-x64q-j89r-v4ry-2gy8;
}

# /etc/nginx/rtmp-apps/stream_48.conf
application stream_48 {
    live on;
    record off;
    allow play all;
    push rtmp://a.rtmp.youtube.com/live2/abc1-def2-ghi3-jkl4-mno5;
}
```

### **🎯 Benefits of Dynamic Apps:**

1. **✅ Isolated Streams**: Each stream has its own nginx application
2. **✅ Unique Stream Keys**: Each stream pushes to correct YouTube channel
3. **✅ Auto Cleanup**: Apps are removed when streams stop
4. **✅ No Conflicts**: Multiple streams can run simultaneously
5. **✅ Hot Reload**: Changes applied without stopping nginx
6. **✅ Scalable**: Supports unlimited concurrent streams

---

## 🐍 **Python Backend Migration Guide**

### **Recommended Python Stack:**
```python
# Core Framework
FastAPI - Modern async web framework
SQLAlchemy - ORM with async support
Alembic - Database migrations
Redis - Pub/sub and caching
Celery - Background task processing
Pydantic - Data validation and serialization

# Additional Libraries
asyncio - Async programming
aioredis - Async Redis client
websockets - Real-time communication
httpx - Async HTTP client
python-multipart - File upload handling
python-jose - JWT token handling
passlib - Password hashing
```

### **Project Structure:**
```
/ezstream-backend
├── app/
│   ├── __init__.py
│   ├── main.py                 # FastAPI application
│   ├── config.py              # Configuration settings
│   ├── database.py            # Database connection
│   ├── dependencies.py        # Common dependencies
│   │
│   ├── api/                   # API endpoints
│   │   ├── __init__.py
│   │   ├── auth.py           # Authentication endpoints
│   │   ├── streams.py        # Stream management
│   │   ├── files.py          # File upload/management
│   │   ├── vps.py            # VPS management
│   │   └── users.py          # User management
│   │
│   ├── models/               # SQLAlchemy models
│   │   ├── __init__.py
│   │   ├── user.py
│   │   ├── stream.py
│   │   ├── vps.py
│   │   ├── file.py
│   │   └── package.py
│   │
│   ├── schemas/              # Pydantic schemas
│   │   ├── __init__.py
│   │   ├── user.py
│   │   ├── stream.py
│   │   ├── vps.py
│   │   └── file.py
│   │
│   ├── services/             # Business logic
│   │   ├── __init__.py
│   │   ├── auth_service.py
│   │   ├── stream_service.py
│   │   ├── file_service.py
│   │   ├── vps_service.py
│   │   └── redis_service.py
│   │
│   ├── tasks/                # Celery tasks
│   │   ├── __init__.py
│   │   ├── stream_tasks.py
│   │   ├── vps_tasks.py
│   │   └── file_tasks.py
│   │
│   ├── core/                 # Core utilities
│   │   ├── __init__.py
│   │   ├── security.py       # Password hashing, JWT
│   │   ├── redis_client.py   # Redis connection
│   │   └── exceptions.py     # Custom exceptions
│   │
│   └── utils/                # Helper functions
│       ├── __init__.py
│       ├── ssh.py           # SSH utilities
│       ├── cdn.py           # BunnyCDN integration
│       └── validators.py    # Custom validators
│
├── alembic/                  # Database migrations
├── tests/                    # Test files
├── requirements.txt          # Python dependencies
├── docker-compose.yml        # Development environment
└── README.md
```

### **Step 1: Database Models (SQLAlchemy)**

#### **models/user.py**
```python
from sqlalchemy import Column, BigInteger, String, DateTime, Boolean, ForeignKey
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.database import Base

class User(Base):
    __tablename__ = "users"

    id = Column(BigInteger, primary_key=True, index=True)
    name = Column(String(255), nullable=False)
    email = Column(String(255), unique=True, index=True, nullable=False)
    email_verified_at = Column(DateTime, nullable=True)
    password_hash = Column(String(255), nullable=False)
    package_id = Column(BigInteger, ForeignKey("packages.id"), nullable=True)
    storage_used = Column(BigInteger, default=0)
    is_active = Column(Boolean, default=True)
    created_at = Column(DateTime, server_default=func.now())
    updated_at = Column(DateTime, server_default=func.now(), onupdate=func.now())

    # Relationships
    package = relationship("Package", back_populates="users")
    streams = relationship("StreamConfiguration", back_populates="user")
    video_files = relationship("VideoFile", back_populates="user")
```

#### **models/stream.py**
```python
from sqlalchemy import Column, BigInteger, String, Text, Enum, JSON, Boolean, DateTime, ForeignKey
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.database import Base
import enum

class StreamStatus(str, enum.Enum):
    INACTIVE = "INACTIVE"
    STARTING = "STARTING"
    STREAMING = "STREAMING"
    STOPPING = "STOPPING"
    ERROR = "ERROR"

class PlaylistOrder(str, enum.Enum):
    SEQUENTIAL = "sequential"
    RANDOM = "random"

class StreamConfiguration(Base):
    __tablename__ = "stream_configurations"

    id = Column(BigInteger, primary_key=True, index=True)
    user_id = Column(BigInteger, ForeignKey("users.id"), nullable=False)
    title = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    status = Column(Enum(StreamStatus), default=StreamStatus.INACTIVE)
    rtmp_url = Column(String(500), nullable=False)
    stream_key = Column(String(255), nullable=False)
    video_files = Column(JSON, nullable=False)
    video_source_path = Column(String(500), nullable=True)
    loop = Column(Boolean, default=False)
    playlist_order = Column(Enum(PlaylistOrder), default=PlaylistOrder.SEQUENTIAL)
    enable_schedule = Column(Boolean, default=False)
    schedule_start_time = Column(DateTime, nullable=True)
    schedule_end_time = Column(DateTime, nullable=True)
    keep_files_after_stop = Column(Boolean, default=False)
    vps_server_id = Column(BigInteger, ForeignKey("vps_servers.id"), nullable=True)
    last_started_at = Column(DateTime, nullable=True)
    last_stopped_at = Column(DateTime, nullable=True)
    created_at = Column(DateTime, server_default=func.now())
    updated_at = Column(DateTime, server_default=func.now(), onupdate=func.now())

    # Relationships
    user = relationship("User", back_populates="streams")
    vps_server = relationship("VpsServer", back_populates="streams")
    progress_records = relationship("StreamProgress", back_populates="stream")
```

### **Step 2: API Endpoints (FastAPI)**

#### **api/streams.py**
```python
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List
from app.database import get_db
from app.models.user import User
from app.models.stream import StreamConfiguration, StreamStatus
from app.schemas.stream import StreamCreate, StreamResponse, StreamUpdate
from app.services.auth_service import get_current_user
from app.tasks.stream_tasks import start_stream_task, stop_stream_task

router = APIRouter(prefix="/streams", tags=["streams"])

@router.get("/", response_model=List[StreamResponse])
async def get_user_streams(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get all streams for current user"""
    streams = db.query(StreamConfiguration).filter(
        StreamConfiguration.user_id == current_user.id
    ).order_by(StreamConfiguration.updated_at.desc()).all()

    return streams

@router.post("/", response_model=StreamResponse)
async def create_stream(
    stream_data: StreamCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Create new stream configuration"""

    # Check user package limits
    active_streams = db.query(StreamConfiguration).filter(
        StreamConfiguration.user_id == current_user.id,
        StreamConfiguration.status.in_([StreamStatus.STARTING, StreamStatus.STREAMING])
    ).count()

    if active_streams >= current_user.package.max_concurrent_streams:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Exceeded maximum concurrent streams for your package"
        )

    # Create stream
    stream = StreamConfiguration(
        user_id=current_user.id,
        **stream_data.dict()
    )

    db.add(stream)
    db.commit()
    db.refresh(stream)

    return stream

@router.post("/{stream_id}/start")
async def start_stream(
    stream_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Start streaming"""

    stream = db.query(StreamConfiguration).filter(
        StreamConfiguration.id == stream_id,
        StreamConfiguration.user_id == current_user.id
    ).first()

    if not stream:
        raise HTTPException(status_code=404, detail="Stream not found")

    if stream.status != StreamStatus.INACTIVE:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Stream is not in inactive state"
        )

    # Update status to STARTING
    stream.status = StreamStatus.STARTING
    db.commit()

    # Dispatch background task
    start_stream_task.delay(stream_id)

    return {"message": "Stream is starting", "stream_id": stream_id}

@router.post("/{stream_id}/stop")
async def stop_stream(
    stream_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Stop streaming"""

    stream = db.query(StreamConfiguration).filter(
        StreamConfiguration.id == stream_id,
        StreamConfiguration.user_id == current_user.id
    ).first()

    if not stream:
        raise HTTPException(status_code=404, detail="Stream not found")

    if stream.status not in [StreamStatus.STARTING, StreamStatus.STREAMING]:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Stream is not running"
        )

    # Update status to STOPPING
    stream.status = StreamStatus.STOPPING
    db.commit()

    # Dispatch background task
    stop_stream_task.delay(stream_id)

    return {"message": "Stream is stopping", "stream_id": stream_id}

@router.delete("/{stream_id}")
async def delete_stream(
    stream_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete stream configuration"""

    stream = db.query(StreamConfiguration).filter(
        StreamConfiguration.id == stream_id,
        StreamConfiguration.user_id == current_user.id
    ).first()

    if not stream:
        raise HTTPException(status_code=404, detail="Stream not found")

    if stream.status in [StreamStatus.STARTING, StreamStatus.STREAMING, StreamStatus.STOPPING]:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete active stream"
        )

    db.delete(stream)
    db.commit()

    return {"message": "Stream deleted successfully"}

@router.get("/{stream_id}/progress")
async def get_stream_progress(
    stream_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get real-time stream progress"""

    stream = db.query(StreamConfiguration).filter(
        StreamConfiguration.id == stream_id,
        StreamConfiguration.user_id == current_user.id
    ).first()

    if not stream:
        raise HTTPException(status_code=404, detail="Stream not found")

    # Get latest progress
    latest_progress = db.query(StreamProgress).filter(
        StreamProgress.stream_id == stream_id
    ).order_by(StreamProgress.created_at.desc()).first()

    return {
        "stream_id": stream_id,
        "status": stream.status,
        "progress": latest_progress.progress if latest_progress else 0,
        "message": latest_progress.message if latest_progress else "",
        "stage": latest_progress.stage if latest_progress else "inactive"
    }
```

### **Step 3: Background Tasks (Celery)**

#### **tasks/stream_tasks.py**
```python
from celery import Celery
from sqlalchemy.orm import Session
from app.database import SessionLocal
from app.models.stream import StreamConfiguration, StreamStatus
from app.models.vps import VpsServer
from app.models.stream import StreamProgress
from app.services.redis_service import RedisService
from app.services.vps_service import VpsService
import json
import logging

logger = logging.getLogger(__name__)

celery_app = Celery('ezstream')

@celery_app.task(bind=True, max_retries=3)
def start_stream_task(self, stream_id: int):
    """Start stream on VPS via Redis command"""

    db: Session = SessionLocal()
    redis_service = RedisService()
    vps_service = VpsService()

    try:
        # Get stream configuration
        stream = db.query(StreamConfiguration).filter(
            StreamConfiguration.id == stream_id
        ).first()

        if not stream:
            raise Exception(f"Stream {stream_id} not found")

        logger.info(f"🚀 Starting stream #{stream_id}")

        # Clear previous progress
        db.query(StreamProgress).filter(
            StreamProgress.stream_id == stream_id
        ).delete()

        # Create initial progress
        progress = StreamProgress(
            stream_id=stream_id,
            stage="preparing",
            progress=5,
            message="Đang gửi lệnh tới VPS..."
        )
        db.add(progress)
        db.commit()

        # Find optimal VPS
        vps = vps_service.find_optimal_vps(db)
        if not vps:
            raise Exception("No available VPS servers")

        # Update stream with VPS assignment
        stream.vps_server_id = vps.id
        vps.current_streams += 1
        db.commit()

        # Prepare Redis command
        redis_command = {
            'command': 'START_STREAM',
            'config': {
                'id': stream.id,
                'video_files': stream.video_files,
                'rtmp_url': stream.rtmp_url,
                'push_urls': None,
                'loop': stream.loop,
                'keep_files_after_stop': stream.keep_files_after_stop,
            }
        }

        # Publish to Redis with retry
        channel = f"vps-commands:{vps.id}"
        publish_result = redis_service.publish_with_retry(
            channel, redis_command, max_retries=3
        )

        logger.info(f"✅ Start command published for stream #{stream_id}")

        # Update progress
        progress = StreamProgress(
            stream_id=stream_id,
            stage="command_sent",
            progress=10,
            message="Lệnh đã gửi tới VPS, đang chờ agent xử lý..."
        )
        db.add(progress)
        db.commit()

    except Exception as e:
        logger.error(f"❌ Start stream task failed for #{stream_id}: {e}")

        # Update stream status to ERROR
        if stream:
            stream.status = StreamStatus.ERROR
            stream.vps_server_id = None
            db.commit()

        # Create error progress
        progress = StreamProgress(
            stream_id=stream_id,
            stage="error",
            progress=0,
            message=f"Task failed: {str(e)}"
        )
        db.add(progress)
        db.commit()

        # Retry task
        raise self.retry(exc=e, countdown=60)

    finally:
        db.close()

@celery_app.task(bind=True, max_retries=3)
def stop_stream_task(self, stream_id: int):
    """Stop stream on VPS via Redis command"""

    db: Session = SessionLocal()
    redis_service = RedisService()

    try:
        # Get stream configuration
        stream = db.query(StreamConfiguration).filter(
            StreamConfiguration.id == stream_id
        ).first()

        if not stream:
            raise Exception(f"Stream {stream_id} not found")

        logger.info(f"🛑 Stopping stream #{stream_id}")

        vps_id = stream.vps_server_id

        if not vps_id:
            logger.warning(f"⚠️ Stream #{stream_id} has no VPS assigned")
            stream.status = StreamStatus.INACTIVE
            stream.last_stopped_at = func.now()
            stream.vps_server_id = None
            db.commit()
            return

        # Create STOP command
        redis_command = {
            'command': 'STOP_STREAM',
            'stream_id': stream_id,
        }

        # Send command via Redis
        channel = f"vps-commands:{vps_id}"
        publish_result = redis_service.publish_with_retry(
            channel, redis_command, max_retries=3
        )

        logger.info(f"✅ Stop command published for stream #{stream_id}")

        # Update status immediately
        stream.status = StreamStatus.INACTIVE
        stream.last_stopped_at = func.now()
        stream.vps_server_id = None
        db.commit()

        # Decrement VPS stream count
        if stream.vps_server and stream.vps_server.current_streams > 0:
            stream.vps_server.current_streams -= 1
            db.commit()

    except Exception as e:
        logger.error(f"❌ Stop stream task failed for #{stream_id}: {e}")

        # Always update status to avoid hanging in STOPPING
        if stream:
            stream.status = StreamStatus.INACTIVE
            stream.last_stopped_at = func.now()
            stream.vps_server_id = None
            db.commit()

        logger.warning(f"⚠️ Stream #{stream_id} marked as INACTIVE despite errors")

    finally:
        db.close()
```

### **Step 4: Redis Listener (AsyncIO)**

#### **services/redis_service.py**
```python
import asyncio
import aioredis
import json
import logging
from typing import Dict, Any
from app.database import SessionLocal
from app.models.stream import StreamConfiguration, StreamProgress, StreamStatus
from app.tasks.stream_tasks import update_stream_status_task

logger = logging.getLogger(__name__)

class RedisService:
    def __init__(self):
        self.redis_url = "redis://localhost:6379"
        self.redis_client = None

    async def connect(self):
        """Connect to Redis"""
        self.redis_client = await aioredis.from_url(self.redis_url)

    async def disconnect(self):
        """Disconnect from Redis"""
        if self.redis_client:
            await self.redis_client.close()

    def publish_with_retry(self, channel: str, data: Dict[str, Any], max_retries: int = 3) -> int:
        """Publish message to Redis with retry logic"""
        import redis

        redis_client = redis.Redis.from_url(self.redis_url)
        last_exception = None

        for attempt in range(1, max_retries + 1):
            try:
                result = redis_client.publish(channel, json.dumps(data))
                logger.info(f"✅ Redis publish successful on attempt {attempt}")
                return result

            except Exception as e:
                last_exception = e
                logger.warning(f"⚠️ Redis publish attempt {attempt} failed: {e}")

                if attempt < max_retries:
                    wait_time = 2 ** (attempt - 1)
                    time.sleep(wait_time)

        raise Exception(f"Redis publish failed after {max_retries} attempts: {last_exception}")

    async def stream_status_listener(self):
        """Listen for stream status updates from VPS"""

        await self.connect()

        try:
            pubsub = self.redis_client.pubsub()
            await pubsub.subscribe("stream-status", "stream-progress")

            logger.info("🎧 Started Redis stream status listener")

            async for message in pubsub.listen():
                if message['type'] == 'message':
                    await self.process_stream_message(
                        message['channel'].decode(),
                        message['data'].decode()
                    )

        except Exception as e:
            logger.error(f"❌ Redis listener error: {e}")
            raise
        finally:
            await self.disconnect()

    async def process_stream_message(self, channel: str, payload: str):
        """Process incoming stream status/progress messages"""

        try:
            data = json.loads(payload)

            logger.info(f"📨 [{channel}] Received: {payload}")

            if channel == "stream-status":
                await self.handle_status_update(data)
            elif channel == "stream-progress":
                await self.handle_progress_update(data)

        except Exception as e:
            logger.error(f"❌ Error processing message: {e}")

    async def handle_status_update(self, data: Dict[str, Any]):
        """Handle stream status updates"""

        stream_id = data.get('stream_id')
        status = data.get('status')
        message = data.get('message', '')

        if not stream_id or not status:
            logger.warning("⚠️ Invalid status update data")
            return

        # Dispatch task to update database
        update_stream_status_task.delay(stream_id, status, message)

        # Send WebSocket update to frontend (if implemented)
        await self.send_websocket_update(stream_id, {
            'type': 'status',
            'status': status,
            'message': message
        })

    async def handle_progress_update(self, data: Dict[str, Any]):
        """Handle stream progress updates"""

        stream_id = data.get('stream_id')
        stage = data.get('stage')
        progress = data.get('progress', 0)
        message = data.get('message', '')

        if not stream_id or not stage:
            logger.warning("⚠️ Invalid progress update data")
            return

        # Update database
        db = SessionLocal()
        try:
            progress_record = StreamProgress(
                stream_id=stream_id,
                stage=stage,
                progress=progress,
                message=message
            )
            db.add(progress_record)
            db.commit()

        except Exception as e:
            logger.error(f"❌ Failed to save progress: {e}")
        finally:
            db.close()

        # Send WebSocket update to frontend
        await self.send_websocket_update(stream_id, {
            'type': 'progress',
            'stage': stage,
            'progress': progress,
            'message': message
        })

    async def send_websocket_update(self, stream_id: int, data: Dict[str, Any]):
        """Send real-time update to WebSocket clients"""
        # TODO: Implement WebSocket broadcasting
        pass

# Global Redis service instance
redis_service = RedisService()

# Background task to run Redis listener
async def start_redis_listener():
    """Start Redis listener as background task"""
    while True:
        try:
            await redis_service.stream_status_listener()
        except Exception as e:
            logger.error(f"❌ Redis listener crashed: {e}")
            logger.info("🔄 Restarting Redis listener in 5 seconds...")
            await asyncio.sleep(5)
```

### **Step 5: WebSocket for Real-time Updates**

#### **api/websocket.py**
```python
from fastapi import APIRouter, WebSocket, WebSocketDisconnect, Depends
from typing import Dict, List
import json
import asyncio
import logging

logger = logging.getLogger(__name__)

router = APIRouter()

class ConnectionManager:
    def __init__(self):
        # Store active connections by stream_id
        self.active_connections: Dict[int, List[WebSocket]] = {}

    async def connect(self, websocket: WebSocket, stream_id: int):
        await websocket.accept()

        if stream_id not in self.active_connections:
            self.active_connections[stream_id] = []

        self.active_connections[stream_id].append(websocket)
        logger.info(f"🔌 WebSocket connected for stream #{stream_id}")

    def disconnect(self, websocket: WebSocket, stream_id: int):
        if stream_id in self.active_connections:
            self.active_connections[stream_id].remove(websocket)

            if not self.active_connections[stream_id]:
                del self.active_connections[stream_id]

        logger.info(f"🔌 WebSocket disconnected for stream #{stream_id}")

    async def send_to_stream(self, stream_id: int, data: dict):
        """Send data to all connections for a specific stream"""

        if stream_id not in self.active_connections:
            return

        connections = self.active_connections[stream_id].copy()

        for connection in connections:
            try:
                await connection.send_text(json.dumps(data))
            except Exception as e:
                logger.error(f"❌ WebSocket send error: {e}")
                # Remove broken connection
                self.disconnect(connection, stream_id)

    async def broadcast_to_all(self, data: dict):
        """Broadcast data to all active connections"""

        for stream_id, connections in self.active_connections.items():
            await self.send_to_stream(stream_id, data)

# Global connection manager
manager = ConnectionManager()

@router.websocket("/ws/stream/{stream_id}")
async def websocket_endpoint(websocket: WebSocket, stream_id: int):
    """WebSocket endpoint for real-time stream updates"""

    await manager.connect(websocket, stream_id)

    try:
        # Send initial connection confirmation
        await websocket.send_text(json.dumps({
            "type": "connection",
            "message": f"Connected to stream #{stream_id}",
            "stream_id": stream_id
        }))

        # Keep connection alive and handle incoming messages
        while True:
            try:
                # Wait for client messages (ping/pong, etc.)
                data = await websocket.receive_text()
                message = json.loads(data)

                if message.get("type") == "ping":
                    await websocket.send_text(json.dumps({
                        "type": "pong",
                        "timestamp": message.get("timestamp")
                    }))

            except WebSocketDisconnect:
                break
            except Exception as e:
                logger.error(f"❌ WebSocket message error: {e}")
                break

    except WebSocketDisconnect:
        pass
    finally:
        manager.disconnect(websocket, stream_id)

# Function to send updates from Redis listener
async def send_stream_update(stream_id: int, update_data: dict):
    """Send stream update via WebSocket"""
    await manager.send_to_stream(stream_id, update_data)
```

### **Step 6: Main Application Setup**

#### **main.py**
```python
from fastapi import FastAPI, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
import asyncio
import uvicorn

from app.api import auth, streams, files, vps, websocket
from app.database import engine, Base
from app.services.redis_service import start_redis_listener

# Create database tables
Base.metadata.create_all(bind=engine)

# Create FastAPI app
app = FastAPI(
    title="EZStream API",
    description="Streaming management system",
    version="2.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:3000"],  # React frontend
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(auth.router, prefix="/api")
app.include_router(streams.router, prefix="/api")
app.include_router(files.router, prefix="/api")
app.include_router(vps.router, prefix="/api")
app.include_router(websocket.router)

# Static files (if needed)
app.mount("/static", StaticFiles(directory="static"), name="static")

@app.on_event("startup")
async def startup_event():
    """Start background tasks"""
    # Start Redis listener in background
    asyncio.create_task(start_redis_listener())

@app.get("/")
async def root():
    return {"message": "EZStream API v2.0"}

@app.get("/health")
async def health_check():
    return {"status": "healthy", "version": "2.0.0"}

if __name__ == "__main__":
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
```

### **Step 7: Configuration**

#### **config.py**
```python
from pydantic import BaseSettings
from typing import Optional

class Settings(BaseSettings):
    # Database
    database_url: str = "mysql+pymysql://root:@localhost/ezstream"

    # Redis
    redis_url: str = "redis://localhost:6379"

    # Security
    secret_key: str = "your-secret-key-here"
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 30

    # BunnyCDN
    bunny_cdn_api_key: str
    bunny_cdn_storage_zone: str = "ezstream"
    bunny_cdn_base_url: str = "https://ezstream.b-cdn.net"

    # Celery
    celery_broker_url: str = "redis://localhost:6379/0"
    celery_result_backend: str = "redis://localhost:6379/0"

    # File Upload
    max_file_size: int = 10 * 1024 * 1024 * 1024  # 10GB
    allowed_file_types: list = ["video/mp4"]

    class Config:
        env_file = ".env"

settings = Settings()
```

#### **requirements.txt**
```txt
fastapi==0.104.1
uvicorn[standard]==0.24.0
sqlalchemy==2.0.23
alembic==1.12.1
pymysql==1.1.0
cryptography==41.0.7
redis==5.0.1
aioredis==2.0.1
celery==5.3.4
pydantic==2.5.0
python-multipart==0.0.6
python-jose[cryptography]==3.3.0
passlib[bcrypt]==1.7.4
httpx==0.25.2
websockets==12.0
```

---

## 🚀 **Migration Steps**

### **Phase 1: Setup Python Environment (Week 1)**
```bash
# 1. Create Python project
mkdir ezstream-backend
cd ezstream-backend
python -m venv venv
source venv/bin/activate  # Linux/Mac
# or
venv\Scripts\activate     # Windows

# 2. Install dependencies
pip install -r requirements.txt

# 3. Setup database migrations
alembic init alembic
alembic revision --autogenerate -m "Initial migration"
alembic upgrade head

# 4. Setup Celery worker
celery -A app.tasks worker --loglevel=info

# 5. Start Redis listener
python -c "import asyncio; from app.services.redis_service import start_redis_listener; asyncio.run(start_redis_listener())"
```

### **Phase 2: Migrate Core Features (Week 2-3)**
1. **Database Models** - Port Laravel models to SQLAlchemy
2. **Authentication** - JWT-based auth system
3. **Stream Management** - Core CRUD operations
4. **Background Tasks** - Celery tasks for start/stop streams
5. **Redis Communication** - Pub/sub with VPS agents

### **Phase 3: Advanced Features (Week 4-5)**
1. **File Upload** - Async file handling with progress
2. **WebSocket** - Real-time updates
3. **VPS Management** - SSH operations and monitoring
4. **Admin Panel** - Management interface

### **Phase 4: Frontend Migration (Week 6-8)**
1. **React/Vue Setup** - Modern frontend framework
2. **API Integration** - REST API consumption
3. **WebSocket Integration** - Real-time updates
4. **UI/UX** - Responsive design

### **Phase 5: Testing & Deployment (Week 9-10)**
1. **Unit Tests** - pytest for backend
2. **Integration Tests** - API endpoint testing
3. **Load Testing** - Performance validation
4. **Production Deployment** - Docker containers

---

## 📊 **Migration Benefits**

### **Performance Improvements:**
- ✅ **Async Operations** - Better concurrent request handling
- ✅ **WebSocket Support** - Real-time updates without polling
- ✅ **Better Redis Handling** - Native async Redis client
- ✅ **Efficient Background Tasks** - Celery with proper retry logic

### **Development Experience:**
- ✅ **Better Debugging** - Python stack traces more readable
- ✅ **Type Safety** - Pydantic schemas and type hints
- ✅ **Modern Framework** - FastAPI with automatic API docs
- ✅ **Testing** - pytest ecosystem

### **Scalability:**
- ✅ **Horizontal Scaling** - Multiple worker processes
- ✅ **Load Balancing** - Better support for multiple instances
- ✅ **Resource Management** - More efficient memory usage
- ✅ **Monitoring** - Better observability tools

---

## 🎯 **Final Notes**

### **What to Keep (VPS Side):**
- ✅ **agent.py** - Python VPS agent with dynamic nginx apps (PERFECT!)
- ✅ **nginx.conf** - RTMP configuration with dynamic app includes
- ✅ **FFmpeg commands** - Video processing pipeline
- ✅ **Redis pub/sub** - Communication protocol
- ✅ **File download logic** - BunnyCDN integration
- ✅ **Dynamic nginx apps** - Stream-specific configurations with unique stream keys

### **What to Migrate (Backend):**
- ❌ **Laravel Framework** → FastAPI
- ❌ **Livewire Components** → React/Vue components
- ❌ **Laravel Queue** → Celery tasks
- ❌ **PHP Redis Client** → aioredis
- ❌ **Blade Templates** → Modern frontend

### **Estimated Timeline:**
- **Backend Migration:** 6-8 weeks
- **Frontend Migration:** 4-6 weeks
- **Testing & Deployment:** 2-3 weeks
- **Total:** 12-17 weeks

**Kết luận:** Migration sang Python sẽ giải quyết được các vấn đề debugging, real-time communication, và scalability. VPS agent đã hoàn hảo với dynamic nginx apps! 🚀

---

## 🔥 **IMPORTANT: VPS Agent Updates**

### **Latest Enhancement - Dynamic Nginx Apps:**

The VPS agent (`agent.py`) has been significantly improved with dynamic nginx application creation:

#### **Key Improvements:**
1. **🎯 Stream-Specific Apps**: Each stream gets its own nginx application (`stream_{id}`)
2. **🔑 Dynamic Stream Keys**: Uses actual YouTube stream key from Laravel config
3. **🔄 Auto Management**: Creates apps on start, removes on stop
4. **⚡ Hot Reload**: Nginx reloads automatically without interruption
5. **📁 Organized Config**: Apps stored in `/etc/nginx/rtmp-apps/` directory

#### **Migration Considerations:**

When migrating to Python backend, ensure the Redis command format includes `stream_key`:

```python
# Python Backend - Updated Redis Command
redis_command = {
    'command': 'START_STREAM',
    'config': {
        'id': stream.id,
        'stream_key': stream.stream_key,  # ✅ REQUIRED for dynamic apps
        'video_files': stream.video_files,
        'rtmp_url': stream.rtmp_url,
        'loop': stream.loop,
        'keep_files_after_stop': stream.keep_files_after_stop,
    }
}
```

#### **Nginx Setup for Migration:**

```nginx
# /etc/nginx/nginx.conf - Main config
rtmp {
    server {
        listen 1935;
        chunk_size 4096;
        allow publish 127.0.0.1;
        deny publish all;

        # ✅ Include dynamic apps directory
        include /etc/nginx/rtmp-apps/*.conf;
    }
}
```

#### **VPS Provisioning Update:**

When provisioning new VPS servers, ensure the dynamic apps directory exists:

```bash
# Add to VPS provisioning script
mkdir -p /etc/nginx/rtmp-apps
chown nginx:nginx /etc/nginx/rtmp-apps
chmod 755 /etc/nginx/rtmp-apps
```

This enhancement makes the streaming system **production-ready** with proper stream isolation and YouTube integration! 🎉
