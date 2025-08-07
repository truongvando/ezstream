<?php

namespace Database\Factories;

use App\Models\StreamLog;
use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamLogFactory extends Factory
{
    protected $model = StreamLog::class;

    public function definition(): array
    {
        return [
            'stream_id' => StreamConfiguration::factory(),
            'event' => $this->faker->randomElement([
                'Stream started',
                'Stream stopped',
                'Playlist updated',
                'Quality metrics updated',
                'Error occurred',
                'Agent communication',
                'User action performed'
            ]),
            'level' => $this->faker->randomElement(['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']),
            'category' => $this->faker->randomElement([
                'STREAM_LIFECYCLE',
                'PLAYLIST_MANAGEMENT',
                'QUALITY_MONITORING',
                'ERROR_RECOVERY',
                'AGENT_COMMUNICATION',
                'PERFORMANCE',
                'USER_ACTION'
            ]),
            'context' => [
                'test_data' => $this->faker->word,
                'timestamp' => now()->toISOString(),
                'additional_info' => $this->faker->sentence
            ],
            'user_id' => User::factory(),
            'vps_id' => VpsServer::factory(),
            'session_id' => $this->faker->uuid
        ];
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'ERROR',
            'event' => 'Error: ' . $this->faker->sentence,
            'category' => 'ERROR_RECOVERY'
        ]);
    }

    public function playlist(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'PLAYLIST_MANAGEMENT',
            'event' => 'Playlist ' . $this->faker->randomElement(['updated', 'reordered', 'video added', 'video removed'])
        ]);
    }

    public function quality(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'QUALITY_MONITORING',
            'event' => 'Quality metrics updated',
            'context' => [
                'bitrate' => $this->faker->numberBetween(1000, 5000),
                'fps' => $this->faker->numberBetween(24, 60),
                'dropped_frames' => $this->faker->numberBetween(0, 10),
                'quality_score' => $this->faker->numberBetween(50, 100)
            ]
        ]);
    }
}
