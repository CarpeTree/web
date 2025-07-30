<?php
// Migration script to add GPS columns to live database
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    // Check if columns already exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM customers LIKE 'geo_latitude'");
    $stmt->execute();
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo json_encode([
            'success' => true,
            'message' => 'GPS columns already exist',
            'action' => 'none'
        ]);
        exit;
    }
    
    // Add GPS columns (without transaction - DDL statements auto-commit)
    $pdo->exec("ALTER TABLE customers 
        ADD COLUMN geo_latitude DECIMAL(10, 8) DEFAULT NULL,
        ADD COLUMN geo_longitude DECIMAL(11, 8) DEFAULT NULL,
        ADD COLUMN geo_accuracy FLOAT DEFAULT NULL,
        ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL,
        ADD COLUMN user_agent TEXT DEFAULT NULL");
    
    // Add indexes
    $pdo->exec("CREATE INDEX idx_customers_geo ON customers (geo_latitude, geo_longitude)");
    $pdo->exec("CREATE INDEX idx_customers_ip ON customers (ip_address)");
    
    echo json_encode([
        'success' => true,
        'message' => 'GPS columns added successfully',
        'action' => 'added_columns'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>