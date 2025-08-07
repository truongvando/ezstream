<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--compress : Compress backup with gzip} {--remote : Upload to remote storage}';
    protected $description = 'Create database backup with optional compression and remote upload';

    public function handle()
    {
        $this->info('🗄️ Starting database backup...');
        
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupDir = '/var/backups/ezstream';
        $filename = "database_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";
        
        // Create backup directory
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
            $this->info("📁 Created backup directory: {$backupDir}");
        }
        
        // Database credentials
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );
        
        $this->info("💾 Creating backup: {$filename}");
        
        // Execute backup
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error("❌ Backup failed with return code: {$returnCode}");
            Log::error('Database backup failed', [
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode
            ]);
            return 1;
        }
        
        // Check file size
        $fileSize = filesize($filepath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        if ($fileSize < 1000) {
            $this->error("❌ Backup file too small ({$fileSize} bytes) - may be corrupted");
            return 1;
        }
        
        $this->info("✅ Backup created: {$fileSizeMB} MB");
        
        // Compress if requested
        if ($this->option('compress')) {
            $this->info("🗜️ Compressing backup...");
            exec("gzip {$filepath}", $output, $returnCode);
            
            if ($returnCode === 0) {
                $compressedSize = filesize("{$filepath}.gz");
                $compressedSizeMB = round($compressedSize / 1024 / 1024, 2);
                $compressionRatio = round((1 - $compressedSize / $fileSize) * 100, 1);
                
                $this->info("✅ Compressed: {$compressedSizeMB} MB ({$compressionRatio}% reduction)");
                $filepath = "{$filepath}.gz";
            } else {
                $this->error("❌ Compression failed");
            }
        }
        
        // Remote upload if requested
        if ($this->option('remote')) {
            $this->info("☁️ Uploading to remote storage...");
            // TODO: Implement S3/Bunny upload
            $this->warn("⚠️ Remote upload not implemented yet");
        }
        
        // Cleanup old backups (keep last 7 days)
        $this->cleanupOldBackups($backupDir);
        
        Log::info('Database backup completed', [
            'file' => $filepath,
            'size_mb' => $fileSizeMB,
            'compressed' => $this->option('compress')
        ]);
        
        $this->info("🎉 Backup completed: {$filepath}");
        return 0;
    }
    
    private function cleanupOldBackups(string $backupDir): void
    {
        $this->info("🧹 Cleaning up old backups...");
        
        $cutoffDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $command = "find {$backupDir} -name 'database_*.sql*' -type f ! -newermt '{$cutoffDate}' -delete";
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->info("✅ Old backups cleaned (kept last 7 days)");
        } else {
            $this->warn("⚠️ Cleanup failed or no old backups found");
        }
    }
}
