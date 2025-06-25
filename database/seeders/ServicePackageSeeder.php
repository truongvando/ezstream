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
                'price' => 199000, // 199k VND
                'max_streams' => 2,
                'storage_limit' => 5 * 1024 * 1024 * 1024, // 5GB in bytes
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
                'price' => 399000, // 399k VND
                'max_streams' => 5,
                'storage_limit' => 20 * 1024 * 1024 * 1024, // 20GB in bytes
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
                'price' => 999000, // 999k VND
                'max_streams' => 20,
                'storage_limit' => 100 * 1024 * 1024 * 1024, // 100GB in bytes
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
