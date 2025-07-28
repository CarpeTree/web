<?php
// Test quote submission without files to see if that's what's hanging
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$debug_messages = [];

try {
    // Skip file upload requirements for this test
    $_FILES = []; // Clear any file uploads
    
    $debug_messages[] = 'Starting test...';
    
    // Load config
    require_once 'server/config/database-simple.php';
    $debug_messages[] = 'Config loaded';
    
    // Validate email
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    $debug_messages[] = 'Email validated';
    
    // Start transaction
    $pdo->beginTransaction();
    $debug_messages[] = 'Transaction started';
    
    // Insert customer
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, referral_source, referrer_name, newsletter_opt_in)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = COALESCE(VALUES(name), name),
        phone = COALESCE(VALUES(phone), phone),
        address = COALESCE(VALUES(address), address)
    ");
    
    $stmt->execute([
        $_POST['email'],
        $_POST['name'] ?? null,
        $_POST['phone'] ?? null,
        $_POST['address'] ?? null,
        $_POST['referralSource'] ?? null,
        $_POST['referrerName'] ?? null,
        isset($_POST['newsletterOptIn']) && $_POST['newsletterOptIn'] === 'true' ? 1 : 0
    ]);
    
    $debug_messages[] = 'Customer inserted';
    
    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }
    
    $debug_messages[] = 'Customer ID: ' . $customer_id;
    
    // Insert quote (WITHOUT files)
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, quote_status, selected_services, 
            gps_lat, gps_lng, exif_lat, exif_lng, notes
        ) VALUES (?, 'submitted', ?, ?, ?, ?, ?, ?)
    ");
    
    $selected_services = isset($_POST['selectedServices']) ? $_POST['selectedServices'] : '[]';
    
    $stmt->execute([
        $customer_id,
        $selected_services,
        $_POST['gpsLat'] ?? null,
        $_POST['gpsLng'] ?? null,
        $_POST['exifLat'] ?? null,
        $_POST['exifLng'] ?? null,
        $_POST['notes'] ?? null
    ]);
    
    $quote_id = $pdo->lastInsertId();
    $debug_messages[] = 'Quote inserted with ID: ' . $quote_id;
    
    // Commit transaction
    $pdo->commit();
    $debug_messages[] = 'Transaction committed';
    
    // Return success
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'message' => 'Quote submitted successfully (no files processed)',
        'debug_messages' => $debug_messages
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'debug_messages' => $debug_messages ?? []
    ]);
}
?> 