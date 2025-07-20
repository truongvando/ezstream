<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find a STREAMING stream to test scheduler
$stream = App\Models\StreamConfiguration::where('status', 'STREAMING')->first();

if ($stream) {
    echo "Found STREAMING stream #{$stream->id}: {$stream->title}\n";
    echo "Current scheduled_end: " . ($stream->scheduled_end ?: 'Not set') . "\n";
    
    // Set it to expire 1 minute ago
    $stream->update([
        'enable_schedule' => true,
        'scheduled_end' => now()->subMinutes(1)
    ]);
    
    echo "✅ Updated stream to test scheduler:\n";
    echo "  - enable_schedule: true\n";
    echo "  - scheduled_end: " . $stream->scheduled_end . " (1 minute ago)\n";
    echo "\nNow run: php artisan streams:check-scheduled\n";
    
} else {
    echo "❌ No STREAMING streams found to test scheduler\n";
    echo "Available streams:\n";
    
    $streams = App\Models\StreamConfiguration::select('id', 'title', 'status')->get();
    foreach ($streams as $s) {
        echo "  - Stream #{$s->id}: {$s->title} ({$s->status})\n";
    }
}
