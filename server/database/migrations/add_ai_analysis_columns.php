<?php
// Migration script to add AI analysis columns to the quotes table
require_once __DIR__ . '/../config/database-simple.php';

try {
    echo "Running migration: Add AI analysis columns...\n";
    
    $pdo->exec("
        ALTER TABLE quotes
        ADD COLUMN ai_o4_mini_analysis JSON DEFAULT NULL AFTER ai_response_json,
        ADD COLUMN ai_o3_analysis JSON DEFAULT NULL AFTER ai_o4_mini_analysis,
        ADD COLUMN ai_gemini_analysis JSON DEFAULT NULL AFTER ai_o3_analysis;
    ");
    
    echo "Migration successful: AI analysis columns added to quotes table.\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>