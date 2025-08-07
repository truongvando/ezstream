#!/usr/bin/env php
<?php
/**
 * Test Script for EZStream Agent v7.0 Compatibility
 * Kiểm tra tất cả files đã được cập nhật đúng lên v7.0
 */

require_once __DIR__ . '/vendor/autoload.php';

class AgentV7CompatibilityTest
{
    private $errors = [];
    private $warnings = [];
    private $successes = [];

    public function run()
    {
        echo "🧪 Testing EZStream Agent v7.0 Compatibility\n";
        echo "==========================================\n\n";

        $this->testAgentPyVersion();
        $this->testConfigPyVersion();
        $this->testCommandHandlerVersion();
        $this->testProvisionScript();
        $this->testProvisionJob();
        $this->testUpdateJob();
        $this->testSimpleStreamManager();

        $this->displayResults();
    }

    private function testAgentPyVersion()
    {
        echo "📝 Testing agent.py version...\n";
        
        $agentPath = 'storage/app/ezstream-agent/agent.py';
        if (!file_exists($agentPath)) {
            $this->errors[] = "agent.py not found at {$agentPath}";
            return;
        }

        $content = file_get_contents($agentPath);
        
        if (strpos($content, 'EZStream Agent v7.0') !== false) {
            $this->successes[] = "agent.py has correct v7.0 version";
        } else {
            $this->errors[] = "agent.py missing v7.0 version reference";
        }

        if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
            $this->successes[] = "agent.py has correct architecture description";
        } else {
            $this->warnings[] = "agent.py missing Simple FFmpeg architecture description";
        }
    }

    private function testConfigPyVersion()
    {
        echo "⚙️ Testing config.py version...\n";
        
        $configPath = 'storage/app/ezstream-agent/config.py';
        if (!file_exists($configPath)) {
            $this->errors[] = "config.py not found at {$configPath}";
            return;
        }

        $content = file_get_contents($configPath);
        
        if (strpos($content, '7.0-simple-ffmpeg') !== false) {
            $this->successes[] = "config.py has correct v7.0 agent version";
        } else {
            $this->errors[] = "config.py missing v7.0 agent version";
        }

        if (strpos($content, 'ffmpeg_reconnect_delay') !== false) {
            $this->successes[] = "config.py has FFmpeg-specific settings";
        } else {
            $this->errors[] = "config.py missing FFmpeg-specific settings";
        }

        if (strpos($content, 'srs_host') !== false) {
            $this->warnings[] = "config.py still contains SRS references (should be removed)";
        }
    }

    private function testCommandHandlerVersion()
    {
        echo "🎛️ Testing command_handler.py version...\n";
        
        $handlerPath = 'storage/app/ezstream-agent/command_handler.py';
        if (!file_exists($handlerPath)) {
            $this->errors[] = "command_handler.py not found at {$handlerPath}";
            return;
        }

        $content = file_get_contents($handlerPath);
        
        if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
            $this->successes[] = "command_handler.py has correct v7.0 description";
        } else {
            $this->errors[] = "command_handler.py missing v7.0 description";
        }
    }

    private function testProvisionScript()
    {
        echo "📜 Testing provision-vps.sh version...\n";
        
        $scriptPath = 'storage/app/ezstream-agent/provision-vps.sh';
        if (!file_exists($scriptPath)) {
            $this->errors[] = "provision-vps.sh not found at {$scriptPath}";
            return;
        }

        $content = file_get_contents($scriptPath);
        
        if (strpos($content, 'SCRIPT v7.0') !== false) {
            $this->successes[] = "provision-vps.sh has correct v7.0 version";
        } else {
            $this->errors[] = "provision-vps.sh missing v7.0 version";
        }

        if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
            $this->successes[] = "provision-vps.sh has correct architecture description";
        } else {
            $this->warnings[] = "provision-vps.sh missing architecture description";
        }
    }

    private function testProvisionJob()
    {
        echo "🏗️ Testing ProvisionMultistreamVpsJob.php...\n";
        
        $jobPath = 'app/Jobs/ProvisionMultistreamVpsJob.php';
        if (!file_exists($jobPath)) {
            $this->errors[] = "ProvisionMultistreamVpsJob.php not found";
            return;
        }

        $content = file_get_contents($jobPath);
        
        if (strpos($content, 'Agent v7.0') !== false) {
            $this->successes[] = "ProvisionJob has v7.0 references";
        } else {
            $this->errors[] = "ProvisionJob missing v7.0 references";
        }

        if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
            $this->successes[] = "ProvisionJob has correct systemd description";
        } else {
            $this->errors[] = "ProvisionJob missing correct systemd description";
        }
    }

    private function testUpdateJob()
    {
        echo "🔄 Testing UpdateAgentJob.php...\n";
        
        $jobPath = 'app/Jobs/UpdateAgentJob.php';
        if (!file_exists($jobPath)) {
            $this->errors[] = "UpdateAgentJob.php not found";
            return;
        }

        $content = file_get_contents($jobPath);
        
        if (strpos($content, 'v7.0') !== false) {
            $this->successes[] = "UpdateJob has v7.0 references";
        } else {
            $this->errors[] = "UpdateJob missing v7.0 references";
        }

        if (strpos($content, 'Simple FFmpeg Direct Streaming') !== false) {
            $this->successes[] = "UpdateJob has correct systemd description";
        } else {
            $this->errors[] = "UpdateJob missing correct systemd description";
        }
    }

    private function testSimpleStreamManager()
    {
        echo "🎬 Testing simple_stream_manager.py...\n";
        
        $managerPath = 'storage/app/ezstream-agent/simple_stream_manager.py';
        if (!file_exists($managerPath)) {
            $this->errors[] = "simple_stream_manager.py not found";
            return;
        }

        $content = file_get_contents($managerPath);
        
        if (strpos($content, 'SimpleStreamManager') !== false) {
            $this->successes[] = "simple_stream_manager.py exists and has main class";
        } else {
            $this->errors[] = "simple_stream_manager.py missing SimpleStreamManager class";
        }

        if (strpos($content, 'FFmpeg Direct') !== false) {
            $this->successes[] = "simple_stream_manager.py has FFmpeg Direct references";
        } else {
            $this->warnings[] = "simple_stream_manager.py missing FFmpeg Direct references";
        }
    }

    private function displayResults()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🧪 TEST RESULTS\n";
        echo str_repeat("=", 50) . "\n\n";

        if (!empty($this->successes)) {
            echo "✅ SUCCESSES (" . count($this->successes) . "):\n";
            foreach ($this->successes as $success) {
                echo "   ✅ {$success}\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠️ WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "   ⚠️ {$warning}\n";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "   ❌ {$error}\n";
            }
            echo "\n";
        }

        $totalTests = count($this->successes) + count($this->warnings) + count($this->errors);
        $successRate = $totalTests > 0 ? round((count($this->successes) / $totalTests) * 100, 1) : 0;

        echo "📊 SUMMARY:\n";
        echo "   Total Tests: {$totalTests}\n";
        echo "   Success Rate: {$successRate}%\n";
        echo "   Errors: " . count($this->errors) . "\n";
        echo "   Warnings: " . count($this->warnings) . "\n\n";

        if (count($this->errors) === 0) {
            echo "🎉 ALL TESTS PASSED! Agent v7.0 compatibility verified.\n";
            exit(0);
        } else {
            echo "❌ TESTS FAILED! Please fix the errors above.\n";
            exit(1);
        }
    }
}

// Run the test
$test = new AgentV7CompatibilityTest();
$test->run();
