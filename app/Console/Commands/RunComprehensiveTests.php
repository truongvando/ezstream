<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\AutoDeleteVideoService;
use App\Services\PlaylistCommandService;
use App\Services\StreamLoggingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RunComprehensiveTests extends Command
{
    protected $signature = 'test:comprehensive 
                            {--scenario= : Specific test scenario to run}
                            {--duration=60 : Test duration in seconds}
                            {--streams=3 : Number of test streams}
                            {--cleanup : Clean up test data after completion}';

    protected $description = 'Run comprehensive tests for auto-delete video system and playlist management';

    private $testResults = [];
    private $startTime;

    public function handle()
    {
        $this->startTime = microtime(true);
        $this->info('🚀 Starting Comprehensive EZStream Tests');
        $this->line('');

        $scenario = $this->option('scenario');
        $duration = (int) $this->option('duration');
        $streamCount = (int) $this->option('streams');

        if ($scenario) {
            $this->runSpecificScenario($scenario);
        } else {
            $this->runAllScenarios($duration, $streamCount);
        }

        $this->displayResults();

        if ($this->option('cleanup')) {
            $this->cleanupTestData();
        }

        return Command::SUCCESS;
    }

    private function runAllScenarios($duration, $streamCount)
    {
        $scenarios = [
            'auto_delete_system' => 'Auto-Delete System Tests',
            'playlist_management' => 'Playlist Management Tests',
            'long_running_streams' => 'Long-Running Stream Tests',
            'error_recovery' => 'Error Recovery Tests',
            'performance_monitoring' => 'Performance Monitoring Tests'
        ];

        foreach ($scenarios as $key => $name) {
            $this->info("📋 Running: {$name}");
            $this->runSpecificScenario($key, $duration, $streamCount);
            $this->line('');
        }
    }

    private function runSpecificScenario($scenario, $duration = 60, $streamCount = 3)
    {
        switch ($scenario) {
            case 'auto_delete_system':
                $this->testAutoDeleteSystem();
                break;
            case 'playlist_management':
                $this->testPlaylistManagement();
                break;
            case 'long_running_streams':
                $this->testLongRunningStreams($duration, $streamCount);
                break;
            case 'error_recovery':
                $this->testErrorRecovery();
                break;
            case 'performance_monitoring':
                $this->testPerformanceMonitoring();
                break;
            default:
                $this->error("Unknown scenario: {$scenario}");
        }
    }

    private function testAutoDeleteSystem()
    {
        $this->info('🗑️ Testing Auto-Delete System...');
        
        try {
            // Create test user and files
            $user = User::factory()->create(['role' => 'user']);
            $files = UserFile::factory()->count(5)->create([
                'user_id' => $user->id,
                'auto_delete_after_stream' => true,
                'status' => 'COMPLETED'
            ]);

            $vps = VpsServer::factory()->create(['status' => 'ACTIVE']);
            $stream = StreamConfiguration::factory()->create([
                'user_id' => $user->id,
                'vps_server_id' => $vps->id,
                'status' => 'STREAMING',
                'video_source_path' => $files->map(fn($f) => ['file_id' => $f->id])->toArray()
            ]);

            $autoDeleteService = app(AutoDeleteVideoService::class);

            // Test 1: Schedule deletion
            $result = $autoDeleteService->scheduleStreamDeletion($stream);
            $this->recordTest('Schedule Stream Deletion', $result['success'], $result['error'] ?? null);

            // Test 2: Process scheduled deletions
            $result = $autoDeleteService->processScheduledDeletions();
            $this->recordTest('Process Scheduled Deletions', $result['success'], $result['error'] ?? null);

            // Test 3: VPS cleanup
            $result = $autoDeleteService->cleanupVpsFiles($vps->id);
            $this->recordTest('VPS Cleanup', $result['success'], $result['error'] ?? null);

            $this->info('✅ Auto-Delete System tests completed');

        } catch (\Exception $e) {
            $this->recordTest('Auto-Delete System', false, $e->getMessage());
            $this->error("❌ Auto-Delete System test failed: {$e->getMessage()}");
        }
    }

    private function testPlaylistManagement()
    {
        $this->info('📋 Testing Playlist Management...');
        
        try {
            $user = User::factory()->create(['role' => 'user']);
            $vps = VpsServer::factory()->create(['status' => 'ACTIVE']);
            
            $files = UserFile::factory()->count(4)->create([
                'user_id' => $user->id,
                'status' => 'COMPLETED'
            ]);

            $stream = StreamConfiguration::factory()->create([
                'user_id' => $user->id,
                'vps_server_id' => $vps->id,
                'status' => 'STREAMING',
                'video_source_path' => $files->take(2)->map(fn($f) => ['file_id' => $f->id])->toArray()
            ]);

            $playlistService = app(PlaylistCommandService::class);

            // Mock Redis for testing
            Redis::shouldReceive('publish')->andReturn(1);

            // Test 1: Update playlist
            $result = $playlistService->updatePlaylist($stream, $files->pluck('id')->toArray());
            $this->recordTest('Update Playlist', $result['success'], $result['error'] ?? null);

            // Test 2: Set loop mode
            $result = $playlistService->setLoopMode($stream, true);
            $this->recordTest('Set Loop Mode', $result['success'], $result['error'] ?? null);

            // Test 3: Set playback order
            $result = $playlistService->setPlaybackOrder($stream, 'random');
            $this->recordTest('Set Playback Order', $result['success'], $result['error'] ?? null);

            // Test 4: Add videos
            $newFiles = UserFile::factory()->count(2)->create([
                'user_id' => $user->id,
                'status' => 'COMPLETED'
            ]);
            $result = $playlistService->addVideos($stream, $newFiles->pluck('id')->toArray());
            $this->recordTest('Add Videos', $result['success'], $result['error'] ?? null);

            // Test 5: Delete videos
            $result = $playlistService->deleteVideos($stream, [$files->first()->id]);
            $this->recordTest('Delete Videos', $result['success'], $result['error'] ?? null);

            $this->info('✅ Playlist Management tests completed');

        } catch (\Exception $e) {
            $this->recordTest('Playlist Management', false, $e->getMessage());
            $this->error("❌ Playlist Management test failed: {$e->getMessage()}");
        }
    }

    private function testLongRunningStreams($duration, $streamCount)
    {
        $this->info("⏱️ Testing Long-Running Streams ({$duration}s, {$streamCount} streams)...");
        
        try {
            $streams = [];
            $loggingService = app(StreamLoggingService::class);

            // Create multiple test streams
            for ($i = 0; $i < $streamCount; $i++) {
                $user = User::factory()->create(['role' => 'user']);
                $vps = VpsServer::factory()->create(['status' => 'ACTIVE']);
                
                $files = UserFile::factory()->count(3)->create([
                    'user_id' => $user->id,
                    'status' => 'COMPLETED'
                ]);

                $stream = StreamConfiguration::factory()->create([
                    'user_id' => $user->id,
                    'vps_server_id' => $vps->id,
                    'status' => 'STREAMING',
                    'loop' => true,
                    'video_source_path' => $files->map(fn($f) => ['file_id' => $f->id])->toArray()
                ]);

                $streams[] = $stream;
                
                // Log stream start
                $loggingService->logStreamEvent(
                    $stream->id,
                    'Long-running test stream started',
                    'INFO',
                    'STREAM_LIFECYCLE',
                    ['test_duration' => $duration, 'stream_index' => $i]
                );
            }

            $startTime = time();
            $endTime = $startTime + $duration;

            // Simulate stream activity
            while (time() < $endTime) {
                foreach ($streams as $stream) {
                    // Simulate quality metrics
                    $loggingService->logQualityMetrics($stream->id, [
                        'bitrate' => rand(2000, 5000),
                        'fps' => rand(25, 30),
                        'dropped_frames' => rand(0, 5),
                        'connection_errors' => rand(0, 2)
                    ]);

                    // Simulate playlist changes
                    if (rand(1, 10) === 1) {
                        $loggingService->logPlaylistChange($stream->id, 'video_transition', [
                            'current_video' => rand(1, 3),
                            'next_video' => rand(1, 3)
                        ]);
                    }
                }

                sleep(5); // Check every 5 seconds
            }

            // Stop streams
            foreach ($streams as $stream) {
                $stream->update(['status' => 'STOPPED']);
                $loggingService->logStreamEvent(
                    $stream->id,
                    'Long-running test stream stopped',
                    'INFO',
                    'STREAM_LIFECYCLE'
                );
            }

            $this->recordTest('Long-Running Streams', true, null);
            $this->info('✅ Long-Running Streams test completed');

        } catch (\Exception $e) {
            $this->recordTest('Long-Running Streams', false, $e->getMessage());
            $this->error("❌ Long-Running Streams test failed: {$e->getMessage()}");
        }
    }

    private function testErrorRecovery()
    {
        $this->info('🔧 Testing Error Recovery...');
        
        try {
            $loggingService = app(StreamLoggingService::class);
            $user = User::factory()->create(['role' => 'user']);
            $vps = VpsServer::factory()->create(['status' => 'ACTIVE']);
            
            $stream = StreamConfiguration::factory()->create([
                'user_id' => $user->id,
                'vps_server_id' => $vps->id,
                'status' => 'STREAMING'
            ]);

            // Simulate various errors
            $errors = [
                'Connection timeout',
                'Bitrate drop detected',
                'Agent communication failed',
                'File not found',
                'Stream quality degraded'
            ];

            foreach ($errors as $error) {
                $loggingService->logErrorWithRecovery(
                    $stream->id,
                    $error,
                    'Attempting automatic recovery',
                    ['error_code' => rand(1000, 9999)]
                );
            }

            $this->recordTest('Error Recovery Logging', true, null);
            $this->info('✅ Error Recovery tests completed');

        } catch (\Exception $e) {
            $this->recordTest('Error Recovery', false, $e->getMessage());
            $this->error("❌ Error Recovery test failed: {$e->getMessage()}");
        }
    }

    private function testPerformanceMonitoring()
    {
        $this->info('📊 Testing Performance Monitoring...');
        
        try {
            $loggingService = app(StreamLoggingService::class);
            
            // Test database performance
            $start = microtime(true);
            
            // Create test data
            $streams = StreamConfiguration::factory()->count(10)->create();
            
            foreach ($streams as $stream) {
                for ($i = 0; $i < 20; $i++) {
                    $loggingService->logPerformanceMetrics($stream->id, [
                        'cpu_usage' => rand(10, 80),
                        'memory_usage' => rand(100, 800),
                        'disk_usage' => rand(20, 90),
                        'network_io' => rand(1000, 10000)
                    ]);
                }
            }
            
            $dbTime = microtime(true) - $start;
            
            // Test Redis performance
            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                Redis::set("test_key_{$i}", json_encode(['test' => 'data']));
                Redis::get("test_key_{$i}");
            }
            $redisTime = microtime(true) - $start;

            $this->recordTest('Database Performance', $dbTime < 5.0, "DB operations took {$dbTime}s");
            $this->recordTest('Redis Performance', $redisTime < 1.0, "Redis operations took {$redisTime}s");
            
            $this->info('✅ Performance Monitoring tests completed');

        } catch (\Exception $e) {
            $this->recordTest('Performance Monitoring', false, $e->getMessage());
            $this->error("❌ Performance Monitoring test failed: {$e->getMessage()}");
        }
    }

    private function recordTest($testName, $success, $error = null)
    {
        $this->testResults[] = [
            'test' => $testName,
            'success' => $success,
            'error' => $error,
            'timestamp' => now()->toISOString()
        ];

        $status = $success ? '✅' : '❌';
        $this->line("  {$status} {$testName}" . ($error ? " - {$error}" : ''));
    }

    private function displayResults()
    {
        $totalTime = microtime(true) - $this->startTime;
        $totalTests = count($this->testResults);
        $passedTests = collect($this->testResults)->where('success', true)->count();
        $failedTests = $totalTests - $passedTests;

        $this->line('');
        $this->info('📊 Test Results Summary');
        $this->line('========================');
        $this->line("Total Tests: {$totalTests}");
        $this->line("Passed: {$passedTests}");
        $this->line("Failed: {$failedTests}");
        $this->line("Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%");
        $this->line("Total Time: " . round($totalTime, 2) . "s");

        if ($failedTests > 0) {
            $this->line('');
            $this->error('❌ Failed Tests:');
            foreach ($this->testResults as $result) {
                if (!$result['success']) {
                    $this->line("  • {$result['test']}: {$result['error']}");
                }
            }
        }
    }

    private function cleanupTestData()
    {
        $this->info('🧹 Cleaning up test data...');
        
        try {
            // Clean up test Redis keys
            $keys = Redis::keys('test_key_*');
            if (!empty($keys)) {
                Redis::del($keys);
            }

            $this->info('✅ Test data cleaned up');
        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
        }
    }
}
