<?php
require_once 'server/config/database-simple.php';

echo "Testing transaction functionality...\n";

try {
    echo "1. Starting transaction...\n";
    $result = $pdo->beginTransaction();
    echo "   beginTransaction() returned: " . ($result ? 'true' : 'false') . "\n";
    echo "   inTransaction(): " . ($pdo->inTransaction() ? 'true' : 'false') . "\n";
    
    echo "2. Doing a simple insert...\n";
    $stmt = $pdo->prepare("INSERT INTO customers (email, name, created_at) VALUES (?, ?, NOW())");
    $stmt->execute(['transaction-test@example.com', 'Transaction Test']);
    $customer_id = $pdo->lastInsertId();
    echo "   Inserted customer ID: $customer_id\n";
    
    echo "3. Committing transaction...\n";
    $pdo->commit();
    echo "   ✅ Transaction committed successfully\n";
    
    echo "4. Verifying data was saved...\n";
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    if ($customer) {
        echo "   ✅ Customer data found: {$customer['email']}\n";
    } else {
        echo "   ❌ Customer data not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($pdo->inTransaction()) {
        echo "Rolling back...\n";
        $pdo->rollback();
    }
}
?> 