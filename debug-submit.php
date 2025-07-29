<?php
// Debug script to test submission process
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SUBMISSION DEBUG ===\n";

try {
    echo "1. Loading database...\n";
    require_once 'server/config/database-simple.php';
    echo "   ✅ Database loaded\n";
    
    echo "2. Testing database connection...\n";
    $test = $pdo->query("SELECT 1")->fetchColumn();
    echo "   ✅ Database connected (result: $test)\n";
    
    echo "3. Starting transaction...\n";
    $pdo->beginTransaction();
    echo "   ✅ Transaction started\n";
    
    echo "4. Testing customer insert...\n";
    $stmt = $pdo->prepare("INSERT INTO customers (email, name, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['debug@test.com', 'Debug User', '555-1234', '123 Test St']);
    $customer_id = $pdo->lastInsertId();
    echo "   ✅ Customer inserted (ID: $customer_id)\n";
    
    echo "5. Testing quote insert...\n";
    $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, selected_services, notes, quote_status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$customer_id, '["pruning"]', 'Debug test', 'submitted']);
    $quote_id = $pdo->lastInsertId();
    echo "   ✅ Quote inserted (ID: $quote_id)\n";
    
    echo "6. Committing transaction...\n";
    $pdo->commit();
    echo "   ✅ Transaction committed\n";
    
    echo "7. SUCCESS! Quote submitted successfully.\n";
    
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    echo "   📍 File: " . $e->getFile() . "\n";
    echo "   📍 Line: " . $e->getLine() . "\n";
    
    if ($pdo && $pdo->inTransaction()) {
        echo "   🔄 Rolling back transaction...\n";
        $pdo->rollback();
        echo "   ✅ Transaction rolled back\n";
    } else {
        echo "   ⚠️  No active transaction to rollback\n";
    }
}
?> 