<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ServicePackage;

class ServicePackageSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Gói Cơ Bản',
                'description' => 'Gói livestream cơ bản cho người mới bắt đầu',
                'price' => 7.65, // ~199k VND (199000/26000)
                'max_streams' => 3,
                'max_video_width' => 1280,
                'max_video_height' => 720,
                'storage_limit_gb' => 5, // 5GB
                'is_active' => true,
                'features' => [
                    'Hỗ trợ 2 stream đồng thời',
                    'Chất lượng tối đa 720p',
                    'Băng thông 50MB/s',
                    'Dung lượng lưu trữ 5GB',
                    'Hỗ trợ 24/7'
                ],
            ],
            [
                'name' => 'Gói Chuyên Nghiệp',
                'description' => 'Gói livestream cho streamer chuyên nghiệp',
                'price' => 15.35, // ~399k VND (399000/26000)
                'max_streams' => 5,
                'max_video_width' => 1920,
                'max_video_height' => 1080,
                'storage_limit_gb' => 20, // 20GB
                'is_active' => true,
                'features' => [
                    'Hỗ trợ 5 stream đồng thời',
                    'Chất lượng tối đa 1080p',
                    'Băng thông 100MB/s',
                    'Dung lượng lưu trữ 20GB',
                    'Recording & replay',
                    'Hỗ trợ ưu tiên'
                ],
            ],
            [
                'name' => 'Gói Doanh Nghiệp',
                'description' => 'Gói livestream cho doanh nghiệp và tổ chức lớn',
                'price' => 38.42, // ~999k VND (999000/26000)
                'max_streams' => 20,
                'max_video_width' => 3840,
                'max_video_height' => 2160,
                'storage_limit_gb' => 100, // 100GB
                'is_active' => true,
                'features' => [
                    'Hỗ trợ 20 stream đồng thời',
                    'Chất lượng tối đa 4K',
                    'Băng thông không giới hạn',
                    'Dung lượng lưu trữ 100GB',
                    'Multi-platform streaming',
                    'Advanced analytics',
                    'Dedicated support'
                ],
            ],
        ];

        foreach ($packages as $package) {
            ServicePackage::firstOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
