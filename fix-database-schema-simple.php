<?php
// Simple Database Schema Fix
require_once 'server/config/database-simple.php';

echo "🔧 Fixing Database Schema...\n";

try {
    // 1. Create email_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quote_id INT DEFAULT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            template_used VARCHAR(50) DEFAULT NULL,
            status ENUM('sent', 'failed') NOT NULL,
            error_message TEXT DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Created email_logs table\n";
    
    // 2. Add location columns to customers (ignore errors if they exist)
    $columns = [
        'ip_address VARCHAR(45) DEFAULT NULL',
        'user_agent TEXT DEFAULT NULL', 
        'geo_latitude DECIMAL(10,8) DEFAULT NULL',
        'geo_longitude DECIMAL(11,8) DEFAULT NULL',
        'geo_accuracy DECIMAL(8,2) DEFAULT NULL'
    ];
    
    foreach ($columns as $column) {
        try {
            $pdo->exec("ALTER TABLE customers ADD COLUMN $column");
            echo "✅ Added column: $column\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠️ Column already exists: $column\n";
            } else {
                throw $e;
            }
        }
    }
    
    // 3. Fix quotes table column name if needed
    try {
        $pdo->exec("ALTER TABLE quotes ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✅ Added created_at to quotes\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠️ quotes.created_at already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n🎉 Database schema fixes completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 