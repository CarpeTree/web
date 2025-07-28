<?php
// Quote submission endpoint WITHOUT file requirement for testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database-simple.php';

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

    // Insert or update customer
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, referral_source, referrer_name, newsletter_opt_in)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = COALESCE(VALUES(name), name),
        phone = COALESCE(VALUES(phone), phone),
        address = COALESCE(VALUES(address), address),
        referral_source = COALESCE(VALUES(referral_source), referral_source),
        referrer_name = COALESCE(VALUES(referrer_name), referrer_name),
        newsletter_opt_in = VALUES(newsletter_opt_in)
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

    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        // Customer already exists, get their ID
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }

    // Insert quote
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, quote_status, selected_services, 
            gps_lat, gps_lng, exif_lat, exif_lng, notes
        ) VALUES (?, 'submitted_no_files', ?, ?, ?, ?, ?, ?)
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

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'message' => 'Quote submitted successfully (without files for testing)'
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