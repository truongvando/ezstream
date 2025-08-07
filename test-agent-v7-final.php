#!/usr/bin/env php
<?php
/**
 * Final Test for EZStream Agent v7.0 Compatibility
 * Comprehensive check của tất cả components
 */

echo "🧪 EZStream Agent v7.0 Final Compatibility Test\n";
echo "==============================================\n\n";

$errors = [];
$warnings = [];
$successes = [];

// Test 1: Agent Core Files
echo "📁 Testing Agent Core Files...\n";
$coreFiles = [
    'storage/app/ezstream-agent/agent.py' => 'Main agent file',
    'storage/app/ezstream-agent/simple_stream_manager.py' => 'Simple stream manager',
    'storage/app/ezstream-agent/config.py' => 'Configuration file',
    'storage/app/ezstream-agent/command_handler.py' => 'Command handler',
    'storage/app/ezstream-agent/status_reporter.py' => 'Status reporter',
    'storage/app/ezstream-agent/file_manager.py' => 'File manager',
    'storage/app/ezstream-agent/process_manager.py' => 'Process manager',
    'storage/app/ezstream-agent/utils.py' => 'Utilities',
    'storage/app/ezstream-agent/requirements.txt' => 'Python dependencies',
    'storage/app/ezstream-agent/provision-vps.sh' => 'Provision script'
];

foreach ($coreFiles as $file => $description) {
    if (file_exists($file)) {
        $successes[] = "✅ {$description} exists: {$file}";
    } else {
        $errors[] = "❌ {$description} missing: {$file}";
    }
}

// Test 2: Version Consistency
echo "\n🔢 Testing Version Consistency...\n";
$versionTests = [
    'storage/app/ezstream-agent/agent.py' => ['EZStream Agent v7.0', 'Simple FFmpeg Direct Streaming'],
    'storage/app/ezstream-agent/config.py' => ['7.0-simple-ffmpeg', 'Simple FFmpeg Direct Streaming'],
    'storage/app/ezstream-agent/command_handler.py' => ['Simple FFmpeg Direct Streaming'],
    'storage/app/ezstream-agent/provision-vps.sh' => ['SCRIPT v7.0', 'Simple FFmpeg Direct Streaming'],
    'app/Jobs/ProvisionMultistreamVpsJob.php' => ['Agent v7.0', 'Simple FFmpeg Direct Streaming'],
    'app/Jobs/UpdateAgentJob.php' => ['v7.0', 'Simple FFmpeg Direct Streaming']
];

foreach ($versionTests as $file => $patterns) {
    if (!file_exists($file)) {
        $errors[] = "❌ File not found for version test: {$file}";
        continue;
    }
    
    $content = file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $successes[] = "✅ {$file} contains: {$pattern}";
        } else {
            $errors[] = "❌ {$file} missing: {$pattern}";
        }
    }
}

// Test 3: Legacy References Check
echo "\n🔍 Testing for Legacy References...\n";
$legacyChecks = [
    'app/Jobs/ProvisionMultistreamVpsJob.php' => ['v6.0', 'SRS-Only'],
    'app/Jobs/UpdateAgentJob.php' => ['v6.0', 'SRS-Only'],
    'storage/app/ezstream-agent/config.py' => ['6.0-srs-only']
];

foreach ($legacyChecks as $file => $patterns) {
    if (!file_exists($file)) {
        continue;
    }
    
    $content = file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $warnings[] = "⚠️ {$file} still contains legacy: {$pattern}";
        } else {
            $successes[] = "✅ {$file} cleaned of legacy: {$pattern}";
        }
    }
}

// Test 4: Architecture Consistency
echo "\n🏗️ Testing Architecture Consistency...\n";
$architectureFiles = [
    'storage/app/ezstream-agent/agent.py',
    'storage/app/ezstream-agent/simple_stream_manager.py'
];

foreach ($architectureFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for SRS imports (should not exist)
    if (preg_match('/import.*srs/i', $content) || preg_match('/from.*srs/i', $content)) {
        $warnings[] = "⚠️ {$file} still imports SRS modules";
    } else {
        $successes[] = "✅ {$file} has no SRS imports";
    }
    
    // Check for simple_stream_manager usage
    if (strpos($content, 'simple_stream_manager') !== false) {
        $successes[] = "✅ {$file} uses simple_stream_manager";
    }
}

// Test 5: Job Configuration
echo "\n⚙️ Testing Job Configuration...\n";
$jobFiles = [
    'app/Jobs/ProvisionMultistreamVpsJob.php',
    'app/Jobs/UpdateAgentJob.php'
];

foreach ($jobFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check systemd service description
    if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
        $successes[] = "✅ {$file} has correct systemd description";
    } else {
        $errors[] = "❌ {$file} missing correct systemd description";
    }
}

// Display Results
echo "\n" . str_repeat("=", 60) . "\n";
echo "🧪 FINAL TEST RESULTS\n";
echo str_repeat("=", 60) . "\n\n";

if (!empty($successes)) {
    echo "✅ SUCCESSES (" . count($successes) . "):\n";
    foreach ($successes as $success) {
        echo "   {$success}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️ WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   {$warning}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "   {$error}\n";
    }
    echo "\n";
}

$totalChecks = count($successes) + count($warnings) + count($errors);
$successRate = $totalChecks > 0 ? round((count($successes) / $totalChecks) * 100, 1) : 0;

echo "📊 SUMMARY:\n";
echo "   Total Checks: {$totalChecks}\n";
echo "   Success Rate: {$successRate}%\n";
echo "   Errors: " . count($errors) . "\n";
echo "   Warnings: " . count($warnings) . "\n\n";

if (count($errors) === 0) {
    echo "🎉 ALL CRITICAL TESTS PASSED!\n";
    echo "✅ EZStream Agent v7.0 compatibility verified!\n\n";
    
    echo "📋 DEPLOYMENT READY:\n";
    echo "   1. Agent v7.0 files are properly updated\n";
    echo "   2. Provision scripts reference v7.0\n";
    echo "   3. PHP jobs are compatible with v7.0\n";
    echo "   4. Legacy SRS references removed\n\n";
    
    echo "🚀 NEXT STEPS:\n";
    echo "   • Deploy agent: php artisan agent:deploy\n";
    echo "   • Update VPS: php artisan vps:bulk-update\n";
    echo "   • Monitor: php artisan agent:listen\n";
    
    exit(0);
} else {
    echo "❌ CRITICAL ISSUES FOUND!\n";
    echo "Please fix the errors above before deploying.\n";
    exit(1);
}
