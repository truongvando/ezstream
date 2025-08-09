<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDatabase extends Command
{
    protected $signature = 'check:database {--export : Export results to file}';
    protected $description = 'Check database structure and compare with expected schema';

    public function handle()
    {
        $this->info('🔍 Checking Database Structure...');
        
        $results = [];
        
        // 1. Basic info
        $dbName = DB::connection()->getDatabaseName();
        $this->info("📊 Database: {$dbName}");
        $results['database'] = $dbName;
        $results['timestamp'] = now()->toISOString();
        
        // 2. Count tables
        $tableCount = $this->getTableCount();
        $this->line("📋 Total tables: {$tableCount}");
        $results['total_tables'] = $tableCount;
        
        // 3. List all tables
        $tables = $this->getAllTables();
        $this->info("\n📝 All Tables:");
        foreach ($tables as $table) {
            $this->line("  • {$table}");
        }
        $results['tables'] = $tables;
        
        // 4. Check critical tables
        $this->info("\n🔍 Checking Critical Tables:");
        $criticalTables = [
            'users', 'user_files', 'stream_configurations', 'jobs', 'failed_jobs',
            'migrations', 'settings', 'vps_servers', 'stream_sessions'
        ];
        
        $missingTables = [];
        foreach ($criticalTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  ✅ {$table} ({$count} records)");
            } else {
                $this->error("  ❌ {$table} - MISSING");
                $missingTables[] = $table;
            }
        }
        $results['missing_critical_tables'] = $missingTables;
        
        // 5. Check YouTube tables
        $this->info("\n📺 YouTube Tables:");
        $youtubeTables = [
            'youtube_channels', 'youtube_videos', 'youtube_video_snapshots',
            'youtube_channel_snapshots', 'youtube_alerts', 'youtube_alert_settings',
            'youtube_ai_analysis'
        ];
        
        $missingYoutube = [];
        foreach ($youtubeTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  ✅ {$table} ({$count} records)");
            } else {
                $this->warn("  ⚠️  {$table} - MISSING");
                $missingYoutube[] = $table;
            }
        }
        $results['missing_youtube_tables'] = $missingYoutube;
        
        // 6. Check user_files structure
        $this->info("\n📁 user_files Structure:");
        $userFilesColumns = $this->getTableColumns('user_files');
        $expectedColumns = [
            'id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size',
            'status', 'stream_video_id', 'stream_metadata', 'auto_delete_after_stream',
            'scheduled_deletion_at', 'created_at', 'updated_at'
        ];
        
        $missingColumns = [];
        foreach ($expectedColumns as $column) {
            if (in_array($column, $userFilesColumns)) {
                $this->line("  ✅ {$column}");
            } else {
                $this->error("  ❌ {$column} - MISSING");
                $missingColumns[] = $column;
            }
        }
        $results['missing_user_files_columns'] = $missingColumns;
        
        // 7. Check recent migrations
        $this->info("\n🔄 Recent Migrations:");
        try {
            $migrations = DB::table('migrations')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get(['migration', 'batch']);
                
            foreach ($migrations as $migration) {
                $this->line("  • {$migration->migration} (batch: {$migration->batch})");
            }
            $results['recent_migrations'] = $migrations->toArray();
        } catch (\Exception $e) {
            $this->error("  ❌ Cannot read migrations table");
            $results['migrations_error'] = $e->getMessage();
        }
        
        // 8. Summary
        $this->info("\n📊 Summary:");
        $this->line("Total Tables: {$tableCount}");
        $this->line("Missing Critical: " . count($missingTables));
        $this->line("Missing YouTube: " . count($missingYoutube));
        $this->line("Missing Columns: " . count($missingColumns));
        
        // 9. Export if requested
        if ($this->option('export')) {
            $filename = "database_check_" . now()->format('Y-m-d_H-i-s') . ".json";
            file_put_contents(storage_path("app/{$filename}"), json_encode($results, JSON_PRETTY_PRINT));
            $this->info("\n💾 Results exported to: storage/app/{$filename}");
        }
        
        // 10. Generate fix commands
        if (count($missingTables) > 0 || count($missingYoutube) > 0 || count($missingColumns) > 0) {
            $this->warn("\n🔧 Suggested fixes:");
            $this->line("php artisan migrate --force");
            if (count($missingYoutube) > 0) {
                $this->line("php artisan migrate --path=database/migrations --force");
            }
        } else {
            $this->info("\n✅ Database structure looks good!");
        }
    }
    
    private function getTableCount()
    {
        $dbName = DB::connection()->getDatabaseName();
        return DB::select("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$dbName])[0]->count;
    }
    
    private function getAllTables()
    {
        $dbName = DB::connection()->getDatabaseName();
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME", [$dbName]);
        return array_map(fn($table) => $table->TABLE_NAME, $tables);
    }
    
    private function getTableColumns($tableName)
    {
        if (!Schema::hasTable($tableName)) {
            return [];
        }
        
        $dbName = DB::connection()->getDatabaseName();
        $columns = DB::select("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$dbName, $tableName]);
        return array_map(fn($col) => $col->COLUMN_NAME, $columns);
    }
}
