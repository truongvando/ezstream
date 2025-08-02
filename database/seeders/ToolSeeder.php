<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tool;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $tools = [
            [
                'name' => 'Video Optimizer Pro',
                'slug' => 'video-optimizer-pro',
                'description' => 'Tool tối ưu hóa video chuyên nghiệp với GPU rendering, hỗ trợ nhiều định dạng và chất lượng cao. Tích hợp AI để tự động điều chỉnh thông số tối ưu.',
                'short_description' => 'Tool tối ưu hóa video chuyên nghiệp với GPU rendering',
                'price' => 299000,
                'sale_price' => 199000,
                'image' => '/images/tools/video-optimizer-pro.jpg',
                'gallery' => [
                    '/images/tools/video-optimizer-1.jpg',
                    '/images/tools/video-optimizer-2.jpg',
                    '/images/tools/video-optimizer-3.jpg'
                ],
                'features' => [
                    'GPU Rendering với CUDA/OpenCL',
                    'Hỗ trợ 50+ định dạng video',
                    'AI Auto-optimization',
                    'Batch processing',
                    'Real-time preview',
                    'Custom presets',
                    'Watermark removal',
                    'Audio enhancement'
                ],
                'system_requirements' => 'Windows 10/11, 8GB RAM, GPU NVIDIA GTX 1060+ hoặc AMD RX 580+',
                'download_url' => '/downloads/video-optimizer-pro.zip',
                'demo_url' => 'https://demo.ezstream.com/video-optimizer',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Stream Manager',
                'slug' => 'stream-manager',
                'description' => 'Quản lý multiple streams đồng thời, auto-restart, monitoring và analytics. Hỗ trợ YouTube, Facebook, Twitch và các platform khác.',
                'short_description' => 'Quản lý multiple streams đồng thời với auto-restart',
                'price' => 199000,
                'sale_price' => null,
                'image' => '/images/tools/stream-manager.jpg',
                'gallery' => [
                    '/images/tools/stream-manager-1.jpg',
                    '/images/tools/stream-manager-2.jpg'
                ],
                'features' => [
                    'Multi-platform streaming',
                    'Auto-restart on failure',
                    'Real-time monitoring',
                    'Stream analytics',
                    'Bandwidth optimization',
                    'Custom RTMP endpoints',
                    'Scheduled streaming',
                    'Chat integration'
                ],
                'system_requirements' => 'Windows 10/11, 4GB RAM, Internet 50Mbps+',
                'download_url' => '/downloads/stream-manager.zip',
                'demo_url' => null,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 2
            ],
            [
                'name' => 'Content Creator Suite',
                'slug' => 'content-creator-suite',
                'description' => 'Bộ công cụ toàn diện cho content creator: thumbnail maker, title generator, SEO optimizer, analytics tracker và nhiều tính năng khác.',
                'short_description' => 'Bộ công cụ toàn diện cho content creator',
                'price' => 399000,
                'sale_price' => 299000,
                'image' => '/images/tools/content-creator-suite.jpg',
                'gallery' => [
                    '/images/tools/content-suite-1.jpg',
                    '/images/tools/content-suite-2.jpg',
                    '/images/tools/content-suite-3.jpg',
                    '/images/tools/content-suite-4.jpg'
                ],
                'features' => [
                    'AI Thumbnail Generator',
                    'Title & Description Optimizer',
                    'SEO Keyword Research',
                    'Analytics Dashboard',
                    'Competitor Analysis',
                    'Content Scheduler',
                    'Hashtag Generator',
                    'Trend Tracker'
                ],
                'system_requirements' => 'Windows 10/11, 6GB RAM, Internet connection',
                'download_url' => '/downloads/content-creator-suite.zip',
                'demo_url' => 'https://demo.ezstream.com/content-suite',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3
            ]
        ];

        foreach ($tools as $tool) {
            Tool::firstOrCreate(
                ['slug' => $tool['slug']],
                $tool
            );
        }
    }
}
