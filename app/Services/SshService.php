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
    protected ?VpsServer $currentVps = null;

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

            // Lấy mật khẩu từ model (đã được xử lý trong accessor)
            $password = $vps->ssh_password;
            
            if (empty($password)) {
                throw new RuntimeException("No valid SSH password for VPS: {$vps->name}");
            }
            
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

            // Store VPS for potential reconnection
            $this->currentVps = $vps;

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
            // Force close any existing channels before executing
            $this->ssh->disconnect();

            // Reconnect with fresh state
            if (!$this->reconnect()) {
                Log::error('Failed to reconnect SSH for command execution');
                return null;
            }

            $output = $this->ssh->exec($command);
            return $output;
        } catch (\Exception $e) {
            Log::error("SSH command execution failed. Error: " . $e->getMessage());

            // Force reconnection on any error
            try {
                $this->forceReconnect();
            } catch (\Exception $reconnectError) {
                Log::warning("Failed to force reconnect SSH: " . $reconnectError->getMessage());
            }
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
     * Reconnect using stored VPS credentials
     */
    private function reconnect(): bool
    {
        if (!$this->currentVps) {
            return false;
        }

        return $this->connect($this->currentVps);
    }

    /**
     * Force reconnection by fully disconnecting first
     */
    private function forceReconnect(): bool
    {
        $this->disconnect();
        return $this->reconnect();
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
            // Use SFTP for file upload to avoid SSH channel conflicts
            // Get connection details from stored VPS info
            $host = $this->currentVps->ip_address;
            $port = $this->currentVps->ssh_port ?? 22;

            $sftp = new SFTP($host, $port);
            $sftp->setTimeout(30); // 30 seconds timeout

            // Login to SFTP (password đã được decrypt bởi model accessor)
            $password = $this->currentVps->ssh_password;
            if (!$sftp->login($this->currentVps->ssh_user, $password)) {
                $sftpErrors = $sftp->getErrors();
                $lastError = $sftp->getLastError();

                Log::error("SFTP login failed", [
                    'host' => $host,
                    'port' => $port,
                    'user' => $this->currentVps->ssh_user,
                    'sftp_errors' => $sftpErrors,
                    'last_error' => $lastError
                ]);
                return false;
            }

            // Ensure remote directory exists
            $remoteDir = dirname($remotePath);
            $mkdirResult = $this->execute("sudo mkdir -p " . escapeshellarg($remoteDir));
            Log::info("Created remote directory: {$remoteDir}", ['result' => $mkdirResult]);

            // Upload file using SFTP
            Log::info("Attempting SFTP upload: {$localPath} to {$remotePath}");

            // Check local file exists and is readable
            if (!file_exists($localPath)) {
                Log::error("Local file does not exist: {$localPath}");
                return false;
            }

            if (!is_readable($localPath)) {
                Log::error("Local file is not readable: {$localPath}");
                return false;
            }

            $localSize = filesize($localPath);
            Log::info("Local file size: {$localSize} bytes");

            // Check if we have write permission to remote directory
            $permCheck = $this->execute("test -w " . escapeshellarg($remoteDir) . " && echo 'WRITABLE' || echo 'NOT_WRITABLE'");
            if (strpos($permCheck, 'NOT_WRITABLE') !== false) {
                Log::warning("Remote directory may not be writable: {$remoteDir}");
                // Try to fix permissions
                $this->execute("sudo chmod 755 " . escapeshellarg($remoteDir));
            }

            // Try SFTP upload with error details
            Log::info("Starting SFTP put operation", [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'local_size' => $localSize
            ]);

            $uploadResult = $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

            if (!$uploadResult) {
                $sftpErrors = $sftp->getErrors();
                $lastError = $sftp->getLastError();
                $serverIdentification = $sftp->getServerIdentification();

                Log::error("SFTP upload failed", [
                    'local_path' => $localPath,
                    'remote_path' => $remotePath,
                    'local_size' => $localSize,
                    'sftp_errors' => $sftpErrors,
                    'last_error' => $lastError,
                    'server_identification' => $serverIdentification,
                    'is_connected' => $sftp->isConnected()
                ]);
                return false;
            }

            // Verify upload
            $stat = $sftp->stat($remotePath);
            $remoteSize = $stat ? $stat['size'] : 0;
            $localSize = filesize($localPath);

            if ($remoteSize !== $localSize) {
                Log::error("File upload verification failed. Expected size: {$localSize}, got: {$remoteSize}");
                return false;
            }

            Log::info("SFTP upload successful: {$localPath} to {$remotePath}");
            return true;

        } catch (\Exception $e) {
            Log::error("SFTP upload failed. Error: " . $e->getMessage(), [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString()
            ]);

            // Fallback to chunked SSH upload
            Log::info("Falling back to chunked SSH upload...");
            return $this->uploadFileViaSSH($localPath, $remotePath);
        }
    }

    /**
     * Fallback method: Upload file via SSH commands (chunked)
     */
    private function uploadFileViaSSH(string $localPath, string $remotePath): bool
    {
        try {
            Log::info("Starting SSH fallback upload for: {$localPath} to {$remotePath}");

            // Read local file content
            $fileContent = file_get_contents($localPath);
            if ($fileContent === false) {
                Log::error("Cannot read local file: {$localPath}");
                return false;
            }

            // Ensure remote directory exists with proper permissions
            $remoteDir = dirname($remotePath);
            $this->execute("sudo mkdir -p " . escapeshellarg($remoteDir));
            $this->execute("sudo chmod 755 " . escapeshellarg($remoteDir));

            // Clear the target file first
            $this->execute("sudo rm -f " . escapeshellarg($remotePath));

            // Use smaller chunks to avoid escapeshellarg() limit
            $chunkSize = 2048; // 2KB chunks to be safe
            $fileSize = strlen($fileContent);
            $chunks = ceil($fileSize / $chunkSize);

            Log::info("SSH fallback: Uploading file in {$chunks} chunks: {$localPath} to {$remotePath}");

            for ($i = 0; $i < $chunks; $i++) {
                $chunk = substr($fileContent, $i * $chunkSize, $chunkSize);
                $base64Chunk = base64_encode($chunk);

                // Append chunk to file
                $uploadCommand = "echo " . escapeshellarg($base64Chunk) . " | base64 -d | sudo tee -a " . escapeshellarg($remotePath) . " > /dev/null";
                $result = $this->execute($uploadCommand);

                // Check if command failed
                if ($result === null) {
                    Log::error("SSH upload chunk {$i} failed - command returned null");
                    return false;
                }

                if ($i % 20 == 0) { // Log progress every 20 chunks
                    Log::info("SSH upload progress: " . round(($i + 1) / $chunks * 100, 1) . "%");
                }
            }

            // Set proper permissions on uploaded file
            $this->execute("sudo chmod 644 " . escapeshellarg($remotePath));

            // Verify upload worked by checking file size
            $verifySize = $this->execute("stat -c%s " . escapeshellarg($remotePath) . " 2>/dev/null || echo '0'");
            $expectedSize = strlen($fileContent);

            if (trim($verifySize) != $expectedSize) {
                Log::error("SSH upload verification failed. Expected size: {$expectedSize}, got: " . trim($verifySize));

                // Additional debug info
                $fileExists = $this->execute("test -f " . escapeshellarg($remotePath) . " && echo 'EXISTS' || echo 'NOT_EXISTS'");
                $filePerms = $this->execute("ls -la " . escapeshellarg($remotePath) . " 2>/dev/null || echo 'NO_PERMS'");

                Log::error("SSH upload debug info", [
                    'file_exists' => trim($fileExists),
                    'file_permissions' => trim($filePerms),
                    'remote_path' => $remotePath
                ]);

                return false;
            }

            Log::info("SSH fallback upload successful: {$localPath} to {$remotePath}");
            return true;

        } catch (\Exception $e) {
            Log::error("SSH fallback upload failed. Error: " . $e->getMessage(), [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'exception_trace' => $e->getTraceAsString()
            ]);
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