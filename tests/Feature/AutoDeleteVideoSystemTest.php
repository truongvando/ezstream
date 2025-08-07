<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\AutoDeleteVideoService;
use App\Services\PlaylistCommandService;
use App\Jobs\ProcessScheduledDeletionsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoDeleteVideoSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $vpsServer;
    protected $autoDeleteService;
    protected $playlistService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'user']);
        $this->vpsServer = VpsServer::factory()->create(['status' => 'ACTIVE']);
        $this->autoDeleteService = app(AutoDeleteVideoService::class);
        $this->playlistService = app(PlaylistCommandService::class);
    }

    /** @test */
    public function it_can_schedule_video_deletion_after_stream_ends()
    {
        // Create test files
        $files = UserFile::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'auto_delete_after_stream' => true,
            'status' => 'COMPLETED'
        ]);

        // Create stream with auto-delete enabled
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING',
            'video_source_path' => $files->map(fn($f) => ['file_id' => $f->id])->toArray()
        ]);

        // Schedule deletion
        $result = $this->autoDeleteService->scheduleStreamDeletion($stream);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('user_files', [
            'id' => $files->first()->id,
            'scheduled_for_deletion' => true
        ]);
    }

    /** @test */
    public function it_can_process_scheduled_deletions()
    {
        // Create files scheduled for deletion
        $files = UserFile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'scheduled_for_deletion' => true,
            'deletion_scheduled_at' => now()->subMinutes(10),
            'auto_delete_after_stream' => true
        ]);

        // Process deletions
        $result = $this->autoDeleteService->processScheduledDeletions();

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['processed_count']);
        
        // Files should be marked as deleted
        foreach ($files as $file) {
            $this->assertDatabaseHas('user_files', [
                'id' => $file->id,
                'status' => 'DELETED'
            ]);
        }
    }

    /** @test */
    public function it_can_update_playlist_in_running_stream()
    {
        // Create stream
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING'
        ]);

        // Create new files to add
        $newFiles = UserFile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'COMPLETED'
        ]);

        // Mock Redis
        Redis::shouldReceive('publish')
            ->once()
            ->with("vps-commands:{$this->vpsServer->id}", \Mockery::type('string'))
            ->andReturn(1);

        // Update playlist
        $result = $this->playlistService->updatePlaylist($stream, $newFiles->pluck('id')->toArray());

        $this->assertTrue($result['success']);
        
        // Check database was updated
        $stream->refresh();
        $this->assertCount(2, $stream->video_source_path);
    }

    /** @test */
    public function it_can_set_loop_mode_for_running_stream()
    {
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING',
            'loop' => false
        ]);

        Redis::shouldReceive('publish')
            ->once()
            ->andReturn(1);

        $result = $this->playlistService->setLoopMode($stream, true);

        $this->assertTrue($result['success']);
        
        $stream->refresh();
        $this->assertTrue($stream->loop);
    }

    /** @test */
    public function it_can_add_videos_to_running_stream()
    {
        // Create stream with existing files
        $existingFiles = UserFile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'COMPLETED'
        ]);

        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING',
            'video_source_path' => $existingFiles->map(fn($f) => ['file_id' => $f->id])->toArray()
        ]);

        // Create new files to add
        $newFiles = UserFile::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'COMPLETED'
        ]);

        Redis::shouldReceive('publish')
            ->once()
            ->andReturn(1);

        $result = $this->playlistService->addVideos($stream, $newFiles->pluck('id')->toArray());

        $this->assertTrue($result['success']);
        
        $stream->refresh();
        $this->assertCount(4, $stream->video_source_path); // 2 existing + 2 new
    }

    /** @test */
    public function it_can_remove_videos_from_running_stream()
    {
        // Create stream with files
        $files = UserFile::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'COMPLETED'
        ]);

        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING',
            'video_source_path' => $files->map(fn($f) => ['file_id' => $f->id])->toArray()
        ]);

        Redis::shouldReceive('publish')
            ->once()
            ->andReturn(1);

        // Remove one file
        $result = $this->playlistService->deleteVideos($stream, [$files->first()->id]);

        $this->assertTrue($result['success']);
        
        $stream->refresh();
        $this->assertCount(2, $stream->video_source_path); // 3 - 1 = 2
    }

    /** @test */
    public function it_prevents_removing_all_videos_from_playlist()
    {
        $file = UserFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'COMPLETED'
        ]);

        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING',
            'video_source_path' => [['file_id' => $file->id]]
        ]);

        $result = $this->playlistService->deleteVideos($stream, [$file->id]);

        $this->assertFalse($result['success']);
        $this->assertStringContains('Cannot delete all videos', $result['error']);
    }

    /** @test */
    public function it_only_processes_files_belonging_to_user()
    {
        $otherUser = User::factory()->create();
        
        // Create file belonging to other user
        $otherUserFile = UserFile::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'COMPLETED'
        ]);

        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING'
        ]);

        $result = $this->playlistService->addVideos($stream, [$otherUserFile->id]);

        $this->assertFalse($result['success']);
        $this->assertStringContains('not found or not accessible', $result['error']);
    }

    /** @test */
    public function it_handles_stream_not_running_error()
    {
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STOPPED'
        ]);

        $result = $this->playlistService->setLoopMode($stream, true);

        $this->assertFalse($result['success']);
        $this->assertStringContains('not currently running', $result['error']);
    }

    /** @test */
    public function scheduled_deletion_job_processes_correctly()
    {
        Queue::fake();

        // Create files scheduled for deletion
        UserFile::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'scheduled_for_deletion' => true,
            'deletion_scheduled_at' => now()->subMinutes(5)
        ]);

        // Dispatch job
        ProcessScheduledDeletionsJob::dispatch();

        Queue::assertPushed(ProcessScheduledDeletionsJob::class);
    }

    /** @test */
    public function it_validates_playback_order_values()
    {
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING'
        ]);

        $result = $this->playlistService->setPlaybackOrder($stream, 'invalid_order');

        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid playback order', $result['error']);
    }

    /** @test */
    public function it_logs_playlist_operations()
    {
        $stream = StreamConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'vps_server_id' => $this->vpsServer->id,
            'status' => 'STREAMING'
        ]);

        Redis::shouldReceive('publish')->andReturn(1);

        $this->playlistService->setLoopMode($stream, true);

        // Check logs were created
        $this->assertDatabaseHas('stream_logs', [
            'stream_id' => $stream->id,
            'category' => 'PLAYLIST_MANAGEMENT'
        ]);
    }
}
