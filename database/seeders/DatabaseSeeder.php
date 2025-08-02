<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ServicePackageSeeder::class,
            VpsServerSeeder::class,
            PaymentSettingsSeeder::class,
            ToolSeeder::class,
            ApiServiceSeeder::class,
        ]);
        
        // Create some sample data for dashboard
        $this->createSampleData();
    }
    
    private function createSampleData()
    {
        // Create sample users
        \App\Models\User::factory(5)->create();
        
        // Create sample VPS servers
        \App\Models\VpsServer::factory(3)->create();
        
        // Create sample stream configurations
        $users = \App\Models\User::where('role', '!=', 'admin')->get();
        $vpsServers = \App\Models\VpsServer::all();
        
        foreach ($users->take(3) as $user) {
            \App\Models\StreamConfiguration::create([
                'user_id' => $user->id,
                'vps_server_id' => $vpsServers->random()->id,
                'title' => 'Sample Stream ' . $user->name,
                'description' => 'Sample stream description for ' . $user->name,
                'video_source_path' => '/tmp/sample_video_' . $user->id . '.mp4',
                'rtmp_url' => 'rtmp://example.com/live/' . uniqid(),
                'stream_key' => 'sk_' . uniqid(),
                'status' => ['ACTIVE', 'INACTIVE', 'PENDING'][array_rand(['ACTIVE', 'INACTIVE', 'PENDING'])],
                'ffmpeg_options' => '-c:v libx264 -c:a aac -b:v 2000k -b:a 128k',
            ]);
        }
        
        // Create sample transactions
        foreach ($users->take(2) as $user) {
            \App\Models\Transaction::create([
                'user_id' => $user->id,
                'amount' => rand(100000, 500000) / 100, // Convert to decimal
                'currency' => 'VND',
                'payment_gateway' => 'bank_transfer',
                'gateway_transaction_id' => 'TXN_' . uniqid(),
                'status' => 'COMPLETED',
                'description' => 'Sample payment for user ' . $user->name,
            ]);
        }
    }
}
