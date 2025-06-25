<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VpsServer>
 */
class VpsServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'ip_address' => $this->faker->unique()->ipv4(),
            'ssh_user' => 'root',
            'ssh_password' => Crypt::encryptString($this->faker->password()),
            'ssh_port' => 22,
            'is_active' => true,
            'status' => 'ACTIVE',
        ];
    }
}
