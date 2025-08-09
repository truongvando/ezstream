<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking Current Database Structure...\n";
echo str_repeat('=', 60) . "\n";

try {
    // Get database name
    $dbName = DB::connection()->getDatabaseName();
    echo "📊 Database: {$dbName}\n\n";

    // Get all tables
    $tables = DB::select("SHOW TABLES");
    $tableKey = "Tables_in_{$dbName}";
    
    echo "📋 All Tables (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        $tableName = $table->$tableKey;
        
        // Get row count
        try {
            $count = DB::table($tableName)->count();
            echo "  • {$tableName} ({$count} records)\n";
        } catch (Exception $e) {
            echo "  • {$tableName} (error counting)\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    
    // Check specific important tables
    $importantTables = [
        'users', 'user_files', 'stream_configurations', 'jobs', 'failed_jobs',
        'migrations', 'settings', 'vps_servers'
    ];
    
    echo "🔍 Important Tables Check:\n";
    foreach ($importantTables as $table) {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            echo "  ✅ {$table} ({$count} records)\n";
        } else {
            echo "  ❌ {$table} - MISSING\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    
    // Check user_files structure if exists
    if (Schema::hasTable('user_files')) {
        echo "📁 user_files Structure:\n";
        $columns = DB::select("DESCRIBE user_files");
        foreach ($columns as $column) {
            echo "  • {$column->Field} ({$column->Type}) - {$column->Null} - {$column->Key}\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    
    // Check stream_configurations structure if exists
    if (Schema::hasTable('stream_configurations')) {
        echo "🎬 stream_configurations Structure:\n";
        $columns = DB::select("DESCRIBE stream_configurations");
        foreach ($columns as $column) {
            echo "  • {$column->Field} ({$column->Type}) - {$column->Null} - {$column->Key}\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    
    // Check migrations table
    if (Schema::hasTable('migrations')) {
        echo "🔄 Recent Migrations:\n";
        $migrations = DB::table('migrations')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['migration', 'batch']);
            
        foreach ($migrations as $migration) {
            echo "  • {$migration->migration} (batch: {$migration->batch})\n";
        }
    } else {
        echo "❌ migrations table not found\n";
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✅ Database structure check completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
