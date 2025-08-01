<?php
// Migration script to add preflight_check_status column to the quotes table
require_once __DIR__ . '/../../config/database-simple.php';

try {
    echo "Running migration: Add preflight_check_status column...\n";
    
    $pdo->exec("
        ALTER TABLE quotes
        ADD COLUMN preflight_check_status JSON DEFAULT NULL AFTER google_event_id;
    ");
    
    echo "Migration successful: preflight_check_status column added to quotes table.\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>