<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ApiService;

class ApiServiceSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $services = [
            [
                'service_id' => 1001,
                'name' => 'YouTube Views - High Quality',
                'type' => 'Default',
                'category' => 'YouTube',
                'rate' => 0.50,
                'min_quantity' => 100,
                'max_quantity' => 100000,
                'refill' => true,
                'cancel' => true,
                'markup_percentage' => 20,
                'is_active' => true
            ],
            [
                'service_id' => 1002,
                'name' => 'YouTube Likes - Real Users',
                'type' => 'Default',
                'category' => 'YouTube',
                'rate' => 1.20,
                'min_quantity' => 50,
                'max_quantity' => 50000,
                'refill' => true,
                'cancel' => true,
                'markup_percentage' => 20,
                'is_active' => true
            ],
            [
                'service_id' => 1003,
                'name' => 'YouTube Subscribers - Premium',
                'type' => 'Default',
                'category' => 'YouTube',
                'rate' => 2.50,
                'min_quantity' => 10,
                'max_quantity' => 10000,
                'refill' => true,
                'cancel' => false,
                'markup_percentage' => 25,
                'is_active' => true
            ],
            [
                'service_id' => 2001,
                'name' => 'Instagram Followers - High Quality',
                'type' => 'Default',
                'category' => 'Instagram',
                'rate' => 1.80,
                'min_quantity' => 100,
                'max_quantity' => 50000,
                'refill' => true,
                'cancel' => true,
                'markup_percentage' => 20,
                'is_active' => true
            ],
            [
                'service_id' => 2002,
                'name' => 'Instagram Likes - Real Users',
                'type' => 'Default',
                'category' => 'Instagram',
                'rate' => 0.80,
                'min_quantity' => 50,
                'max_quantity' => 25000,
                'refill' => true,
                'cancel' => true,
                'markup_percentage' => 20,
                'is_active' => true
            ],
            [
                'service_id' => 3001,
                'name' => 'TikTok Views - Fast Delivery',
                'type' => 'Default',
                'category' => 'TikTok',
                'rate' => 0.30,
                'min_quantity' => 1000,
                'max_quantity' => 1000000,
                'refill' => false,
                'cancel' => true,
                'markup_percentage' => 15,
                'is_active' => true
            ],
            [
                'service_id' => 3002,
                'name' => 'TikTok Followers - Premium',
                'type' => 'Default',
                'category' => 'TikTok',
                'rate' => 2.20,
                'min_quantity' => 100,
                'max_quantity' => 20000,
                'refill' => true,
                'cancel' => false,
                'markup_percentage' => 20,
                'is_active' => true
            ],
            [
                'service_id' => 4001,
                'name' => 'Facebook Page Likes',
                'type' => 'Default',
                'category' => 'Facebook',
                'rate' => 1.50,
                'min_quantity' => 100,
                'max_quantity' => 30000,
                'refill' => true,
                'cancel' => true,
                'markup_percentage' => 20,
                'is_active' => true
            ]
        ];

        foreach ($services as $service) {
            ApiService::firstOrCreate(
                ['service_id' => $service['service_id']],
                $service
            );
        }
    }
}
