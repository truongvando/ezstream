<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tool>
 */
class ToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraphs(3, true),
            'short_description' => $this->faker->sentence(),
            'price' => $this->faker->numberBetween(50000, 500000),
            'sale_price' => $this->faker->optional(0.3)->numberBetween(30000, 400000),
            'image' => '/images/tools/default.jpg',
            'gallery' => [
                '/images/tools/gallery1.jpg',
                '/images/tools/gallery2.jpg'
            ],
            'features' => [
                'Feature 1',
                'Feature 2',
                'Feature 3'
            ],
            'system_requirements' => 'Windows 10/11, 4GB RAM',
            'download_url' => '/downloads/tool.zip',
            'demo_url' => $this->faker->optional()->url(),
            'is_active' => true,
            'is_featured' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the tool is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the tool is on sale.
     */
    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'sale_price' => $attributes['price'] * 0.7, // 30% off
            ];
        });
    }

    /**
     * Indicate that the tool is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
