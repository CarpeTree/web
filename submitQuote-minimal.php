<?php
// Minimal submitQuote for debugging
header('Content-Type: application/json');
require_once __DIR__ . '/server/config/database-simple.php';

try {
    // Validate email
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Create customer
    $stmt = $pdo->prepare("INSERT INTO customers (email, name, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $_POST['email'],
        $_POST['name'] ?? '',
        $_POST['phone'] ?? null,
        $_POST['address'] ?? null
    ]);
    $customer_id = $pdo->lastInsertId();
    
    // Create quote
    $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, selected_services, notes, quote_status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $customer_id,
        $_POST['selectedServices'] ?? '[]',
        $_POST['notes'] ?? '',
        'submitted'
    ]);
    $quote_id = $pdo->lastInsertId();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'message' => 'Quote submitted successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 