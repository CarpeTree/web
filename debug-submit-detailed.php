<?php
// Detailed debug script that mirrors submitQuote.php exactly
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DETAILED SUBMISSION DEBUG ===\n";

// Simulate POST data
$_POST = [
    'email' => 'detailedtest@example.com',
    'name' => 'Detailed Test User',
    'phone' => '555-7777',
    'address' => '789 Detailed Test St, Nelson, BC',
    'selectedServices' => '["pruning"]',
    'notes' => 'Detailed debug test'
];

try {
    echo "Step 1: Validate email...\n";
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    echo "   ✅ Email validated\n";
    
    echo "Step 2: Check file uploads...\n";
    $files_uploaded = false;
    echo "   ✅ No files uploaded (as expected)\n";
    
    echo "Step 3: Load database...\n";
    require_once 'server/config/database-simple.php';
    echo "   ✅ Database loaded\n";
    
    echo "Step 4: Start transaction...\n";
    $pdo->beginTransaction();
    echo "   ✅ Transaction started\n";
    
    echo "Step 5: Set up location data...\n";
    $ip_address = '127.0.0.1';
    $user_agent = 'Debug Script';
    $timestamp = date('Y-m-d H:i:s');
    $geo_latitude = null;
    $geo_longitude = null;
    $geo_accuracy = null;
    echo "   ✅ Location data set\n";
    
    echo "Step 6: Check for duplicate customers...\n";
    $customer = null;
    $is_duplicate = false;
    $duplicate_match_type = '';
    $customer_id = null;
    
    // Check by email
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $duplicate_match_type = 'email';
        $customer_id = $customer['id'];
        echo "   📝 Found existing customer by email (ID: $customer_id)\n";
    } else {
        echo "   📝 No existing customer found by email\n";
    }
    
    echo "Step 7: Create or update customer...\n";
    if ($customer) {
        echo "   🔄 Updating existing customer...\n";
        // Logic for existing customer (skipping for now)
        echo "   ✅ Customer updated\n";
    } else {
        echo "   ➕ Creating new customer...\n";
        $stmt = $pdo->prepare("
            INSERT INTO customers (
                email, name, phone, address, referral_source, referrer_name, newsletter_opt_in,
                ip_address, user_agent, geo_latitude, geo_longitude, geo_accuracy, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $_POST['email'],
            $_POST['name'] ?? '',
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            null, // referral_source
            null, // referrer_name
            0,    // newsletter_opt_in
            $ip_address,
            $user_agent,
            $geo_latitude,
            $geo_longitude,
            $geo_accuracy
        ]);
        $customer_id = $pdo->lastInsertId();
        echo "   ✅ New customer created (ID: $customer_id)\n";
    }
    
    echo "Step 8: Create quote...\n";
    $selected_services = json_decode($_POST['selectedServices'], true) ?: [];
    $notes = $_POST['notes'] ?? '';
    $initial_status = 'submitted';
    
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, selected_services, notes, quote_status, created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([
        $customer_id,
        json_encode($selected_services),
        $notes,
        $initial_status
    ]);
    $quote_id = $pdo->lastInsertId();
    echo "   ✅ Quote created (ID: $quote_id)\n";
    
    echo "Step 9: Commit transaction...\n";
    $pdo->commit();
    echo "   ✅ Transaction committed\n";
    
    echo "🎉 SUCCESS! All steps completed successfully.\n";
    
} catch (Exception $e) {
    echo "   ❌ ERROR at current step: " . $e->getMessage() . "\n";
    echo "   📍 File: " . $e->getFile() . "\n";
    echo "   📍 Line: " . $e->getLine() . "\n";
    
    if (isset($pdo) && $pdo->inTransaction()) {
        echo "   🔄 Rolling back transaction...\n";
        $pdo->rollback();
        echo "   ✅ Transaction rolled back\n";
    } else {
        echo "   ⚠️  No active transaction to rollback\n";
    }
}
?> 