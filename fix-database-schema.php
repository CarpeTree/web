<?php
// Fix missing database tables
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== DATABASE SCHEMA FIX ===\n\n";

try {
    // Check if uploaded_files table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'uploaded_files'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        echo "❌ uploaded_files table missing. Creating...\n";
        
        $create_table_sql = "
        CREATE TABLE uploaded_files (
            id INT PRIMARY KEY AUTO_INCREMENT,
            quote_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_hash VARCHAR(64),
            exif_data JSON,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
            INDEX idx_quote_id (quote_id),
            INDEX idx_filename (filename),
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB;
        ";
        
        $pdo->exec($create_table_sql);
        echo "✅ uploaded_files table created successfully\n";
    } else {
        echo "✅ uploaded_files table already exists\n";
    }
    
    // Check email_log table columns
    echo "\n=== CHECKING EMAIL_LOG TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE email_log");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Email log columns: " . implode(', ', $columns) . "\n";
    
    // Check if we need to add any missing columns
    $required_columns = ['id', 'recipient_email', 'subject', 'template_used', 'quote_id', 'invoice_id', 'sent_at', 'status', 'error_message'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (!empty($missing_columns)) {
        echo "❌ Missing columns in email_log: " . implode(', ', $missing_columns) . "\n";
        
        // Add missing columns based on schema
        if (in_array('sent_at', $missing_columns)) {
            $pdo->exec("ALTER TABLE email_log ADD COLUMN sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✅ Added sent_at column\n";
        }
        if (in_array('status', $missing_columns)) {
            $pdo->exec("ALTER TABLE email_log ADD COLUMN status ENUM('sent', 'failed', 'bounced') DEFAULT 'sent'");
            echo "✅ Added status column\n";
        }
        if (in_array('error_message', $missing_columns)) {
            $pdo->exec("ALTER TABLE email_log ADD COLUMN error_message TEXT");
            echo "✅ Added error_message column\n";
        }
    } else {
        echo "✅ All required columns exist in email_log\n";
    }
    
    // Check quotes table for required columns
    echo "\n=== CHECKING QUOTES TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE quotes");
    $quote_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($quote_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n✅ Database schema check complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 