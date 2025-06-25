<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VpsServer;
use Illuminate\Support\Str;

class VpsProvisionController extends Controller
{
    /**
     * Cung cấp kịch bản cài đặt cho VPS.
     */
    public function getScript($token)
    {
        $vps = VpsServer::where('provision_token', $token)->where('status', 'PENDING_PROVISION')->firstOrFail();

        // Cập nhật trạng thái
        $vps->update(['status' => 'PROVISIONING', 'status_message' => 'VPS has started the provisioning process.']);

        $finish_url = route('vps.provision.finish', ['token' => $token]);

        $script = <<<BASH
#!/bin/bash
set -e

echo "--- [VPS Provisioner] Starting setup... ---"

# --- 1. Update System ---
echo "--- Updating system packages... ---"
apt-get update -y

# --- 2. Install FFmpeg ---
echo "--- Installing FFmpeg... ---"
apt-get install ffmpeg -y

# --- 3. Create Directories ---
echo "--- Creating required directories... ---"
mkdir -p /home/videos/livestream

# --- 4. Report back to main server ---
echo "--- Reporting completion status... ---"
curl -s -X POST "$finish_url"

echo "--- [VPS Provisioner] Setup completed successfully! ---"
BASH;

        return response($script, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Nhận tín hiệu khi VPS đã cài đặt xong.
     */
    public function finish(Request $request, $token)
    {
        $vps = VpsServer::where('provision_token', $token)->where('status', 'PROVISIONING')->firstOrFail();

        $vps->update([
            'status' => 'ACTIVE',
            'status_message' => 'Provisioned successfully via self-report.',
            'provisioned_at' => now(),
            'provision_token' => null, // Vô hiệu hóa token sau khi dùng
        ]);

        return response()->json(['status' => 'success', 'message' => 'VPS status updated to ACTIVE.']);
    }

    /**
     * Hiển thị trang theo dõi và kích hoạt provision thủ công.
     */
    public static function getProvisionStatusPage(VpsServer $vps)
    {
        $vps->refresh(); // Lấy status mới nhất

        // Lấy logs
        $logPath = storage_path('logs/provisioning.log');
        $logs = '';
        if (file_exists($logPath)) {
            $logContent = file_get_contents($logPath);
            $lines = explode("\n", $logContent);
            $relevantLines = array_filter($lines, fn($line) => str_contains($line, "[VPS #{$vps->id}]"));
            $logs = implode("\n", array_slice(array_reverse($relevantLines), 0, 20));
        }

        $run_url = url('/run-provision-job-directly/' . $vps->id);
        $admin_url = route('admin.vps-servers');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VPS Provision Status: {$vps->name}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function runJob() {
            const button = document.getElementById('run-job-btn');
            const iframe = document.getElementById('output-frame');
            iframe.src = 'about:blank'; // Clear previous output
            iframe.classList.remove('hidden');
            button.disabled = true;
            button.innerText = 'Running...';
            
            iframe.src = "{$run_url}";

            iframe.onload = function() {
                button.disabled = false;
                button.innerText = 'Run Provision Job Again';
            };
        }
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-2">⚙️ Provision Status: {$vps->name}</h1>
        <p class="text-gray-600 mb-6">Theo dõi quá trình cài đặt hoặc kích hoạt thủ công.</p>
        
        <div class="mb-4">
            <p><strong>Current Status:</strong> 
                <span id="vps-status" class="font-mono px-2 py-1 rounded text-sm
                    {{ \$vps->status === 'ACTIVE' ? 'bg-green-100 text-green-800' : (\$vps->status === 'PROVISION_FAILED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                    {$vps->status}
                </span>
            </p>
            <p><strong>Status Message:</strong> <span class="text-gray-700">{$vps->status_message}</span></p>
        </div>

        <div class="mb-6">
            <button id="run-job-btn" onclick="runJob()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                ▶️ Run Provision Job Manually
            </button>
            <a href="{$admin_url}" class="ml-4 text-sm text-gray-600 hover:underline">← Back to VPS List</a>
        </div>

        <h2 class="text-xl font-bold mb-2">Live Output / Log</h2>
        <p class="text-sm text-gray-500 mb-4">Nhấn nút "Run" ở trên để xem output trực tiếp bên dưới.</p>
        <iframe id="output-frame" class="w-full h-96 bg-gray-900 text-white font-mono rounded-md border hidden"></iframe>

        <h2 class="text-xl font-bold mt-6 mb-2">Recent Logs</h2>
        <div class="bg-gray-800 text-gray-300 p-4 rounded-md h-64 overflow-y-auto font-mono text-sm">
            <pre>{$logs}</pre>
        </div>
    </div>
</body>
</html>
HTML;
        return $html;
    }
}
