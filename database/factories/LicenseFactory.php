<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tool_id' => Tool::factory(),
            'license_key' => $this->generateLicenseKey(),
            'device_id' => null,
            'device_name' => null,
            'device_info' => null,
            'is_active' => true,
            'activated_at' => null,
            'expires_at' => null, // Lifetime license
        ];
    }

    /**
     * Generate a license key in the format XXXX-XXXX-XXXX-XXXX
     */
    private function generateLicenseKey(): string
    {
        return strtoupper(
            Str::random(4) . '-' . 
            Str::random(4) . '-' . 
            Str::random(4) . '-' . 
            Str::random(4)
        );
    }

    /**
     * Indicate that the license is activated.
     */
    public function activated(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_id' => 'device-' . Str::random(8),
            'device_name' => $this->faker->words(2, true) . ' Computer',
            'device_info' => [
                'os' => $this->faker->randomElement(['Windows 10', 'Windows 11', 'macOS', 'Linux']),
                'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            ],
            'activated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the license is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the license is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
