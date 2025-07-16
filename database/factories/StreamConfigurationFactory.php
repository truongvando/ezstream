<?php

namespace Database\Factories;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamConfigurationFactory extends Factory
{
    protected $model = StreamConfiguration::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            'status' => 'INACTIVE',
            'rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
            'stream_key' => $this->faker->uuid(),
            'video_files' => ['test_video.mp4'],
            'loop' => true,
            'enable_schedule' => false,
            'playlist_order' => 'sequential',
            'keep_files_after_stop' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function streaming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'STREAMING',
            'vps_server_id' => VpsServer::factory(),
            'last_started_at' => now(),
        ]);
    }

    public function stopping(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'STOPPING',
            'vps_server_id' => VpsServer::factory(),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ERROR',
            'error_message' => 'Test error message',
        ]);
    }
}
