<?php

namespace App\Services;

use App\Models\VpsServer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Throwable;

class SshService
{
    protected ?SSH2 $ssh = null;

    /**
     * Connect to the VPS server.
     *
     * @param VpsServer $vps
     * @return bool
     */
    public function connect(VpsServer $vps): bool
    {
        try {
            Log::info("Attempting SSH connection", [
                'vps_name' => $vps->name,
                'ip' => $vps->ip_address,
                'port' => $vps->ssh_port,
                'user' => $vps->ssh_user
            ]);

            $this->ssh = new SSH2($vps->ip_address, $vps->ssh_port);

            // Add connection timeout
            $this->ssh->setTimeout(30); // 30 seconds timeout

            // Fix SSH banner exchange compatibility
            $this->ssh->setWindowColumns(80);
            $this->ssh->setWindowRows(24);

            // Set compatible SSH client identification
            $this->ssh->_identifier = 'SSH-2.0-phpseclib_3.0';

            // Additional compatibility settings
            $this->ssh->setPreferredAlgorithms([
                'kex' => ['diffie-hellman-group14-sha256', 'diffie-hellman-group14-sha1'],
                'hostkey' => ['ssh-rsa', 'ssh-ed25519'],
                'client_to_server' => [
                    'crypt' => ['aes128-ctr', 'aes192-ctr', 'aes256-ctr'],
                    'mac' => ['hmac-sha2-256', 'hmac-sha1'],
                    'comp' => ['none']
                ],
                'server_to_client' => [
                    'crypt' => ['aes128-ctr', 'aes192-ctr', 'aes256-ctr'],
                    'mac' => ['hmac-sha2-256', 'hmac-sha1'],
                    'comp' => ['none']
                ]
            ]);

            // Password is already decrypted by the model's encrypted cast
            $password = $vps->ssh_password;
            
            Log::info("SSH connection established, attempting login", [
                'vps_name' => $vps->name,
                'password_length' => strlen($password)
            ]);

            if (!$this->ssh->login($vps->ssh_user, $password)) {
                $lastError = $this->ssh->getLastError();
                $this->ssh = null;
                throw new RuntimeException("SSH login failed for VPS: {$vps->name}. Error: " . ($lastError ?: 'Unknown error.'));
            }
            
            Log::info("SSH login successful", ['vps_name' => $vps->name]);
            return true;
        } catch (Throwable $e) {
            Log::error("SSH connection exception for VPS: {$vps->name}. Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute a command on the connected server.
     *
     * @param string $command
     * @return string|null
     */
    public function execute(string $command): ?string
    {
        if (!$this->ssh || !$this->ssh->isConnected()) {
            Log::error('SSH is not connected. Cannot execute command.');
            return null;
        }

        try {
            $output = $this->ssh->exec($command);
            return $output;
        } catch (\Exception $e) {
            Log::error("SSH command execution failed. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the exit code of the last command.
     *
     * @return int|null
     */
    public function getExitCode(): ?int
    {
        if (!$this->ssh || !$this->ssh->isConnected()) {
            return null;
        }
        return $this->ssh->getExitStatus();
    }

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void
    {
        if ($this->ssh && $this->ssh->isConnected()) {
            $this->ssh->disconnect();
        }
        $this->ssh = null;
    }

    /**
     * Execute a command to get the PID of a background process.
     * The command should output the PID as the last line.
     *
     * @param string $command
     * @return int|null
     */
    public function executeInBackgroundAndGetPid(string $command): ?int
    {
        // This is a common way to run a command in the background and get its PID
        $commandWithPid = "nohup {$command} > /dev/null 2>&1 & echo $!";
        
        $output = $this->execute($commandWithPid);

        if ($output === null) {
            return null;
        }

        $pid = (int) trim($output);
        return $pid > 0 ? $pid : null;
    }

    /**
     * Kill a process by its PID.
     *
     * @param int $pid
     * @return bool
     */
    public function killProcess(int $pid): bool
    {
        $output = $this->execute("kill -9 {$pid}");
        // Killing a process doesn't usually return output on success.
        // We can check if the process still exists, but for simplicity, we assume it works.
        // A more robust check would be `ps -p $pid`. If it returns nothing, the process is gone.
        return $this->execute("ps -p {$pid}") === '';
    }

    /**
     * Read the content of a remote file.
     *
     * @param string $remotePath
     * @return string|null
     */
    public function readFile(string $remotePath): ?string
    {
        if (!$this->ssh || !$this->ssh->isConnected()) {
            Log::error('SSH is not connected. Cannot read file.');
            return "Error: SSH not connected.";
        }
        
        // Use 'tail' to get the last 100 lines to avoid huge output
        return $this->execute("tail -n 100 " . escapeshellarg($remotePath));
    }

    /**
     * Upload a file to the VPS via SSH command (more reliable than SFTP).
     * Handles large files by chunking the upload.
     *
     * @param string $localPath The path to the local file on the Laravel server.
     * @param string $remotePath The destination path on the remote VPS.
     * @return bool
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        if (!$this->ssh || !$this->ssh->isConnected()) {
            Log::error('SSH is not connected. Cannot upload file.');
            return false;
        }

        try {
            // Read local file content
            $fileContent = file_get_contents($localPath);
            if ($fileContent === false) {
                Log::error("Cannot read local file: {$localPath}");
                return false;
            }

            // Ensure the remote directory exists
            $remoteDir = dirname($remotePath);
            $this->execute("sudo mkdir -p " . escapeshellarg($remoteDir));

            // Clear the target file first
            $this->execute("sudo rm -f " . escapeshellarg($remotePath));

            // For large files, upload in chunks to avoid command line length limits
            $chunkSize = 4096; // 4KB chunks
            $fileSize = strlen($fileContent);
            $chunks = ceil($fileSize / $chunkSize);

            Log::info("Uploading file in {$chunks} chunks: {$localPath} to {$remotePath}");

            for ($i = 0; $i < $chunks; $i++) {
                $chunk = substr($fileContent, $i * $chunkSize, $chunkSize);
                $base64Chunk = base64_encode($chunk);

                // Append chunk to file
                $uploadCommand = "echo " . escapeshellarg($base64Chunk) . " | base64 -d | sudo tee -a " . escapeshellarg($remotePath) . " > /dev/null";
                $result = $this->execute($uploadCommand);

                if ($i % 10 == 0) { // Log progress every 10 chunks
                    Log::info("Upload progress: " . round(($i + 1) / $chunks * 100, 1) . "%");
                }
            }

            // Verify upload worked by checking file size
            $verifySize = $this->execute("stat -c%s " . escapeshellarg($remotePath) . " 2>/dev/null || echo '0'");
            $expectedSize = strlen($fileContent);

            if (trim($verifySize) != $expectedSize) {
                Log::error("File upload verification failed. Expected size: {$expectedSize}, got: " . trim($verifySize));
                return false;
            }

            Log::info("SSH upload successful: {$localPath} to {$remotePath}");
            return true;

        } catch (\Exception $e) {
            Log::error("SSH upload failed. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get CPU usage percentage
     */
    public function getCpuUsage(VpsServer $vps): float
    {
        if (!$this->connect($vps)) {
            throw new RuntimeException("Cannot connect to VPS: {$vps->name}");
        }

        try {
            // Get CPU usage using top command
            $output = $this->execute("top -bn1 | grep 'Cpu(s)' | awk '{print $2}' | cut -d'%' -f1");
            $cpuUsage = floatval(trim($output));
            
            $this->disconnect();
            return min(100, max(0, $cpuUsage));
        } catch (\Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * Get RAM usage percentage
     */
    public function getRamUsage(VpsServer $vps): float
    {
        if (!$this->connect($vps)) {
            throw new RuntimeException("Cannot connect to VPS: {$vps->name}");
        }

        try {
            // Get memory usage exactly like htop calculation
            // htop formula: (MemTotal - MemFree - Buffers - Cached) / MemTotal * 100
            $output = $this->execute("awk '/MemTotal/ {total=$2} /MemFree/ {free=$2} /^Buffers/ {buffers=$2} /^Cached/ {cached=$2} END {printf \"%.2f\", (total-free-buffers-cached)/total*100}' /proc/meminfo");
            $ramUsage = floatval(trim($output));
            
            $this->disconnect();
            return min(100, max(0, $ramUsage));
        } catch (\Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * Get disk usage percentage
     */
    public function getDiskUsage(VpsServer $vps): float
    {
        if (!$this->connect($vps)) {
            throw new RuntimeException("Cannot connect to VPS: {$vps->name}");
        }

        try {
            // Get disk usage for root partition
            $output = $this->execute("df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1");
            $diskUsage = floatval(trim($output));
            
            $this->disconnect();
            return min(100, max(0, $diskUsage));
        } catch (\Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * Get available disk space in GB
     */
    public function getAvailableDiskSpace(VpsServer $vps): float
    {
        if (!$this->connect($vps)) {
            throw new RuntimeException("Cannot connect to VPS: {$vps->name}");
        }

        try {
            // Get available disk space for root partition in GB
            $output = $this->execute("df -BG / | awk 'NR==2{print $4}' | cut -d'G' -f1");
            $availableSpace = floatval(trim($output));
            
            $this->disconnect();
            return $availableSpace;
        } catch (\Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * Get system uptime
     */
    public function getUptime(VpsServer $vps): string
    {
        if (!$this->connect($vps)) {
            throw new RuntimeException("Cannot connect to VPS: {$vps->name}");
        }

        try {
            $output = $this->execute("uptime -p");
            $this->disconnect();
            return trim($output) ?: 'Unknown';
        } catch (\Exception $e) {
            $this->disconnect();
            throw $e;
        }
    }
} 