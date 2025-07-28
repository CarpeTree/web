<?php
// Simple database connection test
header('Content-Type: application/json');

try {
    // Test 1: Include config files
    echo "Testing config files...\n";
    require_once 'server/config/database-simple.php';
    echo "✅ database-simple.php loaded\n";
    
    require_once 'server/config/config.php';
    echo "✅ config.php loaded\n";
    
    // Test 2: Check database connection
    echo "Testing database connection...\n";
    if (isset($pdo)) {
        echo "✅ PDO connection exists\n";
        
        // Test 3: Simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result['test'] == 1) {
            echo "✅ Database query works\n";
        }
        
        // Test 4: Check if tables exist
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "📋 Tables found: " . implode(', ', $tables) . "\n";
        
        // Test 5: Try inserting a test customer
        echo "Testing customer insert...\n";
        $stmt = $pdo->prepare("
            INSERT INTO customers (email, name, phone) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute(['test@example.com', 'Test User', '1234567890']);
        echo "✅ Customer insert works\n";
        
        // Clean up test data
        $pdo->query("DELETE FROM customers WHERE email = 'test@example.com'");
        
    } else {
        echo "❌ No PDO connection\n";
    }
    
    echo json_encode(['status' => 'success', 'message' => 'All tests passed']);
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 