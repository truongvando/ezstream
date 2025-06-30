<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING DOWNLOADED FILES ===\n\n";

// Check temp downloads folder
$tempDir = storage_path('app/temp_downloads');
echo "Temp downloads directory: {$tempDir}\n";

if (is_dir($tempDir)) {
    $files = scandir($tempDir);
    $files = array_filter($files, function($file) {
        return $file !== '.' && $file !== '..';
    });
    
    if (empty($files)) {
        echo "❌ No files found in temp downloads\n";
    } else {
        echo "✅ Found " . count($files) . " file(s):\n";
        foreach ($files as $file) {
            $filePath = $tempDir . '/' . $file;
            $size = filesize($filePath);
            $modified = date('Y-m-d H:i:s', filemtime($filePath));
            echo "  📁 {$file} - {$size} bytes - Modified: {$modified}\n";
            
            // Check if it's a video file
            if ($size > 1024) {
                $header = file_get_contents($filePath, false, null, 0, 100);
                if (str_contains($header, 'ftyp') || str_contains($header, 'RIFF')) {
                    echo "     ✅ Appears to be a valid video file\n";
                } else {
                    echo "     ❌ May not be a valid video file\n";
                    echo "     Header: " . bin2hex(substr($header, 0, 50)) . "\n";
                }
            }
        }
    }
} else {
    echo "❌ Temp downloads directory does not exist\n";
}

echo "\n";

// Check recent user files
echo "=== RECENT USER FILES ===\n";
$recentFiles = App\Models\UserFile::where('created_at', '>=', now()->subHour())
    ->orderBy('created_at', 'desc')
    ->get();

if ($recentFiles->isEmpty()) {
    echo "❌ No recent user files\n";
} else {
    foreach ($recentFiles as $file) {
        echo "ID: {$file->id} - {$file->original_name} - Status: {$file->status}\n";
        echo "  Size: {$file->size} bytes - Disk: {$file->disk}\n";
        echo "  Error: " . ($file->error_message ?? 'None') . "\n";
        echo "  Created: {$file->created_at}\n\n";
    }
} 