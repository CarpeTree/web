<?php
// Simplified version without duplicate detection
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    // Validate required fields
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Start database transaction
    $pdo->beginTransaction();
    
    // Simple customer creation (no duplicate detection)
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, referral_source, referrer_name)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['email'],
        $_POST['name'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['address'] ?? '',
        $_POST['howDidYouHear'] ?? null,
        $_POST['referrerName'] ?? null
    ]);
    
    $customer_id = $pdo->lastInsertId();
    
    // Create quote record
    $stmt = $pdo->prepare("
        INSERT INTO quotes (customer_id, selected_services, notes)
        VALUES (?, ?, ?)
    ");
    
    $services = $_POST['selectedServices'] ?? '[]';
    
    $stmt->execute([
        $customer_id, 
        $services,
        $_POST['notes'] ?? null
    ]);
    $quote_id = $pdo->lastInsertId();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'message' => 'Quote submitted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 