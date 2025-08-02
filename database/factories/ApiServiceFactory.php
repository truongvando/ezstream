<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiService>
 */
class ApiServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['YouTube', 'Instagram', 'TikTok', 'Facebook', 'Twitter'];
        $types = ['Views', 'Likes', 'Subscribers', 'Followers', 'Comments'];
        
        $category = $this->faker->randomElement($categories);
        $type = $this->faker->randomElement($types);
        
        return [
            'service_id' => $this->faker->unique()->numberBetween(1000, 9999),
            'name' => "{$category} {$type} - High Quality",
            'type' => 'Default',
            'category' => $category,
            'rate' => $this->faker->randomFloat(3, 0.1, 5.0),
            'min_quantity' => $this->faker->numberBetween(10, 100),
            'max_quantity' => $this->faker->numberBetween(1000, 100000),
            'refill' => $this->faker->boolean(70),
            'cancel' => $this->faker->boolean(80),
            'markup_percentage' => $this->faker->numberBetween(15, 30),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the service is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the service is for YouTube.
     */
    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'YouTube',
            'name' => 'YouTube ' . $this->faker->randomElement(['Views', 'Likes', 'Subscribers']) . ' - Premium',
        ]);
    }

    /**
     * Indicate that the service is for Instagram.
     */
    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Instagram',
            'name' => 'Instagram ' . $this->faker->randomElement(['Followers', 'Likes', 'Views']) . ' - High Quality',
        ]);
    }
}
