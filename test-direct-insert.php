<?php
// Direct database insert test
require_once 'server/config/database-simple.php';

echo "=== DIRECT QUOTE INSERT TEST ===\n\n";

try {
    // First, insert a test customer
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, created_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    
    $stmt->execute([
        'directtest@example.com',
        'Direct Test Customer',
        '778-555-9999',
        'Direct Test Address'
    ]);
    
    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute(['directtest@example.com']);
        $customer_id = $stmt->fetchColumn();
    }
    
    echo "✅ Customer ID: $customer_id\n";
    
    // Now try to insert quote with 'submitted' status
    echo "Testing quote insert with status 'submitted'...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, 
            selected_services, 
            notes, 
            quote_status, 
            quote_created_at,
            ai_analysis_complete
        ) VALUES (?, ?, ?, ?, NOW(), 0)
    ");
    
    $test_services = json_encode(['pruning', 'assessment']);
    $test_notes = 'Direct test notes';
    $test_status = 'submitted';
    
    echo "Inserting with:\n";
    echo "- customer_id: $customer_id\n";
    echo "- selected_services: $test_services\n";
    echo "- notes: $test_notes\n";
    echo "- quote_status: $test_status\n";
    
    $stmt->execute([
        $customer_id,
        $test_services,
        $test_notes,
        $test_status
    ]);
    
    $quote_id = $pdo->lastInsertId();
    echo "✅ Quote inserted successfully with ID: $quote_id\n";
    
    // Verify the inserted data
    $verify_stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
    $verify_stmt->execute([$quote_id]);
    $quote_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Verified data:\n";
    echo "- quote_status: '{$quote_data['quote_status']}'\n";
    echo "- selected_services: {$quote_data['selected_services']}\n";
    echo "- notes: {$quote_data['notes']}\n";
    
    $pdo->commit();
    echo "✅ Transaction committed successfully\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ Error Code: " . $e->getCode() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?> 