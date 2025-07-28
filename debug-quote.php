<?php
// Direct quote submission test
header('Content-Type: text/plain');

echo "=== DIRECT QUOTE SUBMISSION TEST ===\n\n";

try {
    // Test 1: Load config
    echo "1. Loading config files...\n";
    require_once 'server/config/database-simple.php';
    echo "✅ Database connection loaded\n";
    
    // Test 2: Simple database test
    echo "\n2. Testing database connection...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $result = $stmt->fetch();
    echo "✅ Database works. Customers: " . $result['count'] . "\n";
    
    // Test 3: Test customer insertion
    echo "\n3. Testing customer insertion...\n";
    $test_email = 'test-' . time() . '@example.com';
    
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $test_email,
        'Test Customer',
        '7786553741', 
        'Test Address'
    ]);
    
    $customer_id = $pdo->lastInsertId();
    echo "✅ Customer inserted with ID: " . $customer_id . "\n";
    
    // Test 4: Test quote insertion
    echo "\n4. Testing quote insertion...\n";
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, status, services_requested, location_description
        ) VALUES (?, 'pending', ?, ?)
    ");
    
    $stmt->execute([
        $customer_id,
        '["sprinklers", "fuel_modification"]',
        'Test quote submission'
    ]);
    
    $quote_id = $pdo->lastInsertId();
    echo "✅ Quote inserted with ID: " . $quote_id . "\n";
    
    // Cleanup
    $pdo->query("DELETE FROM quotes WHERE id = $quote_id");
    $pdo->query("DELETE FROM customers WHERE id = $customer_id");
    echo "\n✅ Test data cleaned up\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    echo "The basic database operations work fine.\n";
    echo "The issue must be in the file upload or form processing logic.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 