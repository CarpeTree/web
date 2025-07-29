<?php
// Minimal version of submitQuote.php - gradually adding features
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Step 1: Basic validation (like the working test)
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Step 2: Try to load database
    require_once __DIR__ . '/../config/database-simple.php';
    
    // Step 3: Try a simple database operation
    $pdo->beginTransaction();
    
    // Create/find customer (simplified)
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        $customer_id = $customer['id'];
    } else {
        // Create new customer
        $stmt = $pdo->prepare("INSERT INTO customers (email, name, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['email'],
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? ''
        ]);
        $customer_id = $pdo->lastInsertId();
    }
    
    // Create quote (simplified)
    $services = $_POST['selectedServices'] ?? '[]';
    $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, selected_services, notes, quote_status) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $customer_id,
        $services,
        $_POST['notes'] ?? '',
        'submitted'
    ]);
    $quote_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Return success (like the real submitQuote.php)
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'uploaded_files' => 0,
        'email_sent' => false,
        'message' => 'Quote submitted successfully. We will contact you to schedule an in-person assessment.',
        'is_duplicate_customer' => ($customer ? true : false),
        'duplicate_match_type' => ($customer ? 'email' : ''),
        'crm_dashboard_url' => "https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}"
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 