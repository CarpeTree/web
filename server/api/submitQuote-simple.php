<?php
// Simplified quote submission without file uploads
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

try {
    echo "=== SIMPLIFIED QUOTE SUBMISSION TEST ===\n";
    
    // Load config
    require_once '../config/database-simple.php';
    require_once '../config/config.php';
    echo "✅ Config loaded\n";
    
    // Validate email
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    echo "✅ Email validated: " . $_POST['email'] . "\n";
    
    // Start transaction
    $pdo->beginTransaction();
    echo "✅ Transaction started\n";
    
    // Insert customer
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
    echo "✅ Customer inserted/updated\n";
    
    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }
    echo "✅ Customer ID: " . $customer_id . "\n";
    
    // Insert quote (WITHOUT files for now)
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, status, services_requested, 
            gps_lat, gps_lng, exif_lat, exif_lng, location_description
        ) VALUES (?, 'pending', ?, ?, ?, ?, ?, ?)
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
    echo "✅ Quote inserted with ID: " . $quote_id . "\n";
    
    // Commit transaction
    $pdo->commit();
    echo "✅ Transaction committed\n";
    
    echo "\n=== SUCCESS ===\n";
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'message' => 'Quote submitted successfully (without files for testing)'
    ]);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 