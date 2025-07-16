<?php

namespace Tests\Feature;

use App\Jobs\StopMultistreamJob;
use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HangingStreamFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_job_handles_redis_connection_failure_gracefully()
    {
        // Create test data
        $user = User::factory()->create();
        $vps = VpsServer::factory()->create(['status' => 'ACTIVE']);

        $stream = StreamConfiguration::factory()->create([
            'user_id' => $user->id,
            'status' => 'STREAMING',
            'vps_server_id' => $vps->id,
        ]);

        // Update to STOPPING status
        $stream->update(['status' => 'STOPPING']);

        // Mock Redis connection failure by using invalid Redis config
        config(['database.redis.default.host' => 'invalid_host']);

        // Execute the job
        $job = new StopMultistreamJob($stream);
        
        // Job should not throw exception and should update stream status
        $job->handle();
        
        // Refresh stream from database
        $stream->refresh();
        
        // Stream should be marked as INACTIVE even if Redis failed
        $this->assertEquals('INACTIVE', $stream->status);
        $this->assertNull($stream->vps_server_id);
        $this->assertNotNull($stream->last_stopped_at);
        $this->assertStringContains('Stop command failed but marked as stopped', $stream->error_message);
    }

    public function test_hanging_streams_are_auto_fixed_on_page_load()
    {
        // Create test data
        $user = User::factory()->create();
        $vps = VpsServer::factory()->create(['status' => 'ACTIVE', 'current_streams' => 1]);

        $stream = StreamConfiguration::factory()->stopping()->create([
            'user_id' => $user->id,
            'vps_server_id' => $vps->id,
            'updated_at' => now()->subMinutes(10), // Stuck for 10 minutes
        ]);

        // Act as the user and visit stream management page
        $response = $this->actingAs($user)->get('/streams');

        // Stream should be auto-fixed
        $stream->refresh();
        $vps->refresh();
        
        $this->assertEquals('INACTIVE', $stream->status);
        $this->assertNull($stream->vps_server_id);
        $this->assertStringContains('Auto-fixed: was stuck in STOPPING status', $stream->error_message);
        $this->assertEquals(0, $vps->current_streams); // VPS stream count decremented
    }

    public function test_force_stop_hanging_streams_command()
    {
        // Create hanging stream
        $user = User::factory()->create();
        $vps = VpsServer::factory()->create(['status' => 'ACTIVE', 'current_streams' => 1]);

        $stream = StreamConfiguration::factory()->stopping()->create([
            'user_id' => $user->id,
            'vps_server_id' => $vps->id,
            'updated_at' => now()->subMinutes(10), // Stuck for 10 minutes
        ]);

        // Run the command
        $this->artisan('streams:force-stop-hanging --timeout=300')
             ->expectsOutput('Found 1 hanging stream(s):')
             ->assertExitCode(0);

        // Stream should be fixed
        $stream->refresh();
        $vps->refresh();
        
        $this->assertEquals('INACTIVE', $stream->status);
        $this->assertNull($stream->vps_server_id);
        $this->assertEquals(0, $vps->current_streams);
    }

    public function test_dry_run_mode_does_not_change_streams()
    {
        // Create hanging stream
        $user = User::factory()->create();
        $stream = StreamConfiguration::factory()->stopping()->create([
            'user_id' => $user->id,
            'updated_at' => now()->subMinutes(10), // Stuck for 10 minutes
        ]);

        // Run dry-run command
        $this->artisan('streams:force-stop-hanging --timeout=300 --dry-run')
             ->expectsOutput('Would force stop this stream')
             ->assertExitCode(0);

        // Stream should remain unchanged
        $stream->refresh();
        $this->assertEquals('STOPPING', $stream->status);
    }
}
