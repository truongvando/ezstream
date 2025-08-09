<?php
// Simple database check without Laravel bootstrap
$host = 'localhost';
$dbname = 'sql_ezstream_pro'; // From .env
$username = 'root';
$password = ''; // Empty password from .env

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 Database Structure Check\n";
    echo "Database: $dbname\n";
    echo str_repeat('=', 50) . "\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 All Tables (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $countStmt->fetchColumn();
        echo "  • $table ($count records)\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n";
    
    // Check user_files structure
    if (in_array('user_files', $tables)) {
        echo "📁 user_files Structure:\n";
        $stmt = $pdo->query("DESCRIBE user_files");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  • {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']}\n";
        }
        echo "\n";
    }
    
    // Check stream_configurations structure
    if (in_array('stream_configurations', $tables)) {
        echo "🎬 stream_configurations Structure:\n";
        $stmt = $pdo->query("DESCRIBE stream_configurations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  • {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']}\n";
        }
        echo "\n";
    }
    
    // Check jobs table
    if (in_array('jobs', $tables)) {
        echo "⚙️ jobs Structure:\n";
        $stmt = $pdo->query("DESCRIBE jobs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  • {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']}\n";
        }
        echo "\n";
    }
    
    // Check migrations
    if (in_array('migrations', $tables)) {
        echo "🔄 Recent Migrations:\n";
        $stmt = $pdo->query("SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 10");
        $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($migrations as $migration) {
            echo "  • {$migration['migration']} (batch: {$migration['batch']})\n";
        }
    } else {
        echo "❌ migrations table not found\n";
    }
    
    echo "\n✅ Check completed!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "💡 Please check your database connection settings\n";
}
?>
