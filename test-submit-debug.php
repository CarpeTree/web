<?php
// Simple test to debug submitQuote.php issues
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Debug: Starting test -->\n";

try {
    require_once __DIR__ . '/server/config/database-simple.php';
    echo "<!-- Debug: Database connected -->\n";
    
    // Test if REGEXP_REPLACE function exists
    $stmt = $pdo->prepare("SELECT VERSION() as mysql_version");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<!-- Debug: MySQL Version: " . $result['mysql_version'] . " -->\n";
    
    // Test REGEXP_REPLACE function
    try {
        $stmt = $pdo->prepare("SELECT REGEXP_REPLACE('123-456-7890', '[^0-9]', '') as cleaned_phone");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<!-- Debug: REGEXP_REPLACE works: " . $result['cleaned_phone'] . " -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: REGEXP_REPLACE failed: " . $e->getMessage() . " -->\n";
    }
    
    echo json_encode(['success' => true, 'message' => 'Test completed']);
    
} catch (Exception $e) {
    echo "<!-- Debug: Error: " . $e->getMessage() . " -->\n";
    echo json_encode(['error' => $e->getMessage()]);
}
?> 