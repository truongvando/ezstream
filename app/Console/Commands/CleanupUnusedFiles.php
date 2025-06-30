<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupUnusedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:unused 
                           {--dry-run : Chỉ hiển thị file sẽ xóa, không thực sự xóa}
                           {--force : Bắt buộc xóa không cần xác nhận}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xóa file không sử dụng để tối ưu hệ thống';

    // File chắc chắn có thể xóa (test files, unused controllers)
    private $definitelyUnused = [
        // Test files
        'resources/views/test-google-drive.blade.php',
        'resources/views/test-streaming.blade.php', 
        'resources/views/webhook-test.blade.php',
        // NOTE: welcome.blade.php là landing page, không xóa!
        
        // Unused controllers (đã được thay thế bởi Livewire)
        'app/Http/Controllers/Admin/AdminController.php',
        'app/Http/Controllers/ChunkedUploadController.php',
        
        // Mock services
        'app/Services/MockSshService.php',
        
        // Unused services (không được reference)
        'app/Services/CDNProxyService.php',
        'app/Services/StreamLifecycleManager.php',
        'app/Services/VpsNetworkManager.php',
    ];

    // File cần review (có thể dùng trong tương lai)
    private $reviewNeeded = [
        // Auth views (có thể dùng nếu custom auth)
        'resources/views/auth/confirm-password.blade.php',
        'resources/views/auth/forgot-password.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/auth/register.blade.php',
        'resources/views/auth/reset-password.blade.php',
        'resources/views/auth/verify-email.blade.php',
        
        // Profile views (có thể dùng nếu custom profile)
        'resources/views/profile/edit.blade.php',
        'resources/views/profile/partials/delete-user-form.blade.php',
        'resources/views/profile/partials/update-password-form.blade.php',
        'resources/views/profile/partials/update-profile-information-form.blade.php',
    ];

    // Migrations cũ có thể gộp
    private $oldMigrations = [
        'database/migrations/2025_06_27_054007_add_playlist_features_to_stream_configurations.php',
        'database/migrations/2025_06_27_100705_make_vps_server_id_nullable_in_stream_configurations_table.php',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Cleanup File Không Sử Dụng');
        $this->line('=' . str_repeat('=', 40));
        
        if ($this->option('dry-run')) {
            $this->warn('⚠️ CHẾ ĐỘ THỬ NGHIỆM - Không thực sự xóa file');
        }
        
        // 1. Hiển thị file sẽ xóa
        $this->displayFilesToDelete();
        
        // 2. Xác nhận từ user (nếu không có --force)
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('Bạn có chắc muốn xóa các file này?')) {
                $this->info('❌ Hủy bỏ cleanup');
                return;
            }
        }
        
        // 3. Thực hiện xóa
        $this->performCleanup();
        
        // 4. Hiển thị kết quả
        $this->displayResults();
    }

    private function displayFilesToDelete()
    {
        $this->info('📋 DANH SÁCH FILE SẼ XÓA:');
        
        $this->line('🗑️ File chắc chắn không dùng:');
        foreach ($this->definitelyUnused as $file) {
            $exists = File::exists(base_path($file)) ? '✓' : '✗';
            $this->line("  {$exists} {$file}");
        }
        
        $this->line('');
        $this->line('⚠️ Migration trùng lặp:');
        foreach ($this->oldMigrations as $file) {
            $exists = File::exists(base_path($file)) ? '✓' : '✗';
            $this->line("  {$exists} {$file}");
        }
        
        $this->line('');
        $this->line('📝 File cần review (không xóa tự động):');
        foreach ($this->reviewNeeded as $file) {
            $exists = File::exists(base_path($file)) ? '✓' : '✗';
            $this->line("  {$exists} {$file}");
        }
    }

    private function performCleanup()
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 Chế độ thử nghiệm - không thực sự xóa file');
            return;
        }
        
        $this->info('🗑️ Bắt đầu xóa file...');
        
        $deleted = 0;
        $errors = 0;
        
        // Xóa file chắc chắn không dùng
        foreach ($this->definitelyUnused as $file) {
            $fullPath = base_path($file);
            
            if (File::exists($fullPath)) {
                try {
                    File::delete($fullPath);
                    $this->line("  ✅ Đã xóa: {$file}");
                    $deleted++;
                } catch (\Exception $e) {
                    $this->line("  ❌ Lỗi xóa {$file}: " . $e->getMessage());
                    $errors++;
                }
            } else {
                $this->line("  ⚠️ File không tồn tại: {$file}");
            }
        }
        
        // Xóa migration trùng lặp
        foreach ($this->oldMigrations as $file) {
            $fullPath = base_path($file);
            
            if (File::exists($fullPath)) {
                try {
                    File::delete($fullPath);
                    $this->line("  ✅ Đã xóa migration: {$file}");
                    $deleted++;
                } catch (\Exception $e) {
                    $this->line("  ❌ Lỗi xóa {$file}: " . $e->getMessage());
                    $errors++;
                }
            }
        }
        
        $this->info("✅ Hoàn thành: Đã xóa {$deleted} file, {$errors} lỗi");
    }

    private function displayResults()
    {
        $this->info('📊 KẾT QUẢ CLEANUP:');
        
        // Tính toán dung lượng tiết kiệm
        $savedSpace = $this->calculateSavedSpace();
        
        $this->table(['Thống Kê', 'Giá Trị'], [
            ['File đã xóa', count($this->definitelyUnused) + count($this->oldMigrations)],
            ['File cần review', count($this->reviewNeeded)],
            ['Dung lượng tiết kiệm', $savedSpace . ' KB'],
        ]);
        
        $this->info('🎯 KHUYẾN NGHỊ TIẾP THEO:');
        $this->line('1. Chạy composer dump-autoload để cập nhật autoloader');
        $this->line('2. Clear cache: php artisan cache:clear');
        $this->line('3. Review file auth views nếu cần custom authentication');
        $this->line('4. Cân nhắc gộp các migration nhỏ thành 1 migration lớn');
        
        $this->warn('⚠️ Lưu ý: Backup database trước khi chạy migration cleanup!');
    }

    private function calculateSavedSpace()
    {
        $totalSize = 0;
        
        $allFiles = array_merge($this->definitelyUnused, $this->oldMigrations);
        
        foreach ($allFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                $totalSize += File::size($fullPath);
            }
        }
        
        return round($totalSize / 1024, 2); // Convert to KB
    }

    /**
     * Tạo backup trước khi cleanup
     */
    private function createBackup()
    {
        $this->info('💾 Tạo backup...');
        
        $backupDir = storage_path('app/cleanup_backup_' . date('Y_m_d_H_i_s'));
        File::makeDirectory($backupDir, 0755, true);
        
        $allFiles = array_merge($this->definitelyUnused, $this->oldMigrations);
        
        foreach ($allFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                $backupPath = $backupDir . '/' . str_replace(['/', '\\'], '_', $file);
                File::copy($fullPath, $backupPath);
            }
        }
        
        $this->line("✅ Backup tạo tại: {$backupDir}");
        return $backupDir;
    }
}
