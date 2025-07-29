<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    if (empty($input['id'])) {
        throw new Exception('Customer ID is required');
    }

    if (empty($input['email'])) {
        throw new Exception('Email is required');
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Update customer information
    $stmt = $pdo->prepare("
        UPDATE customers 
        SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $input['name'] ?? '',
        $input['email'],
        $input['phone'] ?? '',
        $input['address'] ?? '',
        $input['id']
    ]);

    if (!$result) {
        throw new Exception('Failed to update customer');
    }

    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        throw new Exception('Customer not found or no changes made');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Customer updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 