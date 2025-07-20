<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use Illuminate\Console\Command;

class TestPlatformIcons extends Command
{
    protected $signature = 'test:platform-icons';
    protected $description = 'Test platform icons display as SVG';

    public function handle()
    {
        $this->info('🎨 Testing Platform Icons (SVG)');
        $this->line('');

        // Test different platforms
        $platforms = [
            'YouTube' => 'rtmp://a.rtmp.youtube.com/live2',
            'Facebook' => 'rtmp://live-api-s.facebook.com:80/rtmp',
            'Twitch' => 'rtmp://live.twitch.tv/live',
            'TikTok' => 'rtmp://push.tiktok.com/live',
            'Custom' => 'rtmp://custom.example.com/live'
        ];

        foreach ($platforms as $platformName => $rtmpUrl) {
            // Create a temporary stream to test
            $stream = new StreamConfiguration([
                'title' => "Test {$platformName} Stream",
                'rtmp_url' => $rtmpUrl,
                'user_id' => 1
            ]);

            $this->line("Platform: {$platformName}");
            $this->line("RTMP URL: {$rtmpUrl}");
            $this->line("Detected Platform: {$stream->platform}");
            $this->line("Icon HTML: {$stream->platform_icon}");
            $this->line("Icon contains SVG: " . (str_contains($stream->platform_icon, '<svg') ? 'YES' : 'NO'));
            $this->line("Icon contains emoji: " . (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $stream->platform_icon) ? 'YES' : 'NO'));
            $this->line('');
        }

        $this->info('✅ Platform icon test completed!');
        $this->line('');
        $this->info('💡 To see icons in browser:');
        $this->line('1. Go to your stream management page');
        $this->line('2. Look at the "Nền tảng" field in stream cards');
        $this->line('3. Icons should now be colorful SVG instead of emoji');
    }
}
