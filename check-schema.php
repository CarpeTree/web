<?php
// Check actual database schema
header('Content-Type: text/plain');

echo "=== DATABASE SCHEMA CHECK ===\n\n";

try {
    require_once 'server/config/database-simple.php';
    echo "✅ Database connected\n\n";
    
    // Check what tables exist
    echo "1. TABLES:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    // Check quotes table structure
    echo "\n2. QUOTES TABLE STRUCTURE:\n";
    if (in_array('quotes', $tables)) {
        $stmt = $pdo->query("DESCRIBE quotes");
        $columns = $stmt->fetchAll();
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
    } else {
        echo "  ❌ quotes table does not exist!\n";
    }
    
    // Check customers table structure  
    echo "\n3. CUSTOMERS TABLE STRUCTURE:\n";
    if (in_array('customers', $tables)) {
        $stmt = $pdo->query("DESCRIBE customers");
        $columns = $stmt->fetchAll();
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
    } else {
        echo "  ❌ customers table does not exist!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?> 