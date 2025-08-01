<?php
// Migration script to create the ai_cost_log table
require_once __DIR__ . '/../../config/database-simple.php';

try {
    echo "Running migration: Create ai_cost_log table...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_cost_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            quote_id INT NOT NULL,
            model_name VARCHAR(50),
            provider VARCHAR(50),
            input_tokens INT,
            output_tokens INT,
            total_cost DECIMAL(10, 6),
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
        );
    ");
    
    echo "Migration successful: ai_cost_log table created.\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>