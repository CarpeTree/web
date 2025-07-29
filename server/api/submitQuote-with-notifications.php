<?php
// Version with file uploads + admin notifications 
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Basic validation
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Load database
    require_once __DIR__ . '/../config/database-simple.php';
    
    // Check if files were uploaded
    $files_uploaded = !empty($_FILES) && isset($_FILES['files']) && $_FILES['files']['error'][0] !== UPLOAD_ERR_NO_FILE;
    
    $pdo->beginTransaction();
    
    // Handle existing customers with ON DUPLICATE KEY UPDATE (like working version)
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, referral_source, referrer_name)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = COALESCE(VALUES(name), name),
        phone = COALESCE(VALUES(phone), phone),
        address = COALESCE(VALUES(address), address),
        referral_source = COALESCE(VALUES(referral_source), referral_source),
        referrer_name = COALESCE(VALUES(referrer_name), referrer_name)
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
    if (!$customer_id) {
        // If no new insert, get existing customer ID
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }
    
    // Create quote
    $services = $_POST['selectedServices'] ?? '[]';
    $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, selected_services, notes, quote_status) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $customer_id,
        $services,
        $_POST['notes'] ?? '',
        'submitted'
    ]);
    $quote_id = $pdo->lastInsertId();
    
    // Handle file uploads if present
    $uploaded_files = [];
    if ($files_uploaded) {
        $upload_dir = dirname(dirname(__DIR__)) . "/uploads/$quote_id";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $files = $_FILES['files'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = $files['name'][$i];
                $temp_path = $files['tmp_name'][$i];
                $file_path = $upload_dir . '/' . $filename;
                
                if (move_uploaded_file($temp_path, $file_path)) {
                    // Save file info to database
                    $stmt = $pdo->prepare("
                        INSERT INTO uploaded_files (quote_id, filename, file_path, file_size, mime_type, uploaded_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $quote_id,
                        $filename,
                        $file_path,
                        $files['size'][$i],
                        $files['type'][$i]
                    ]);
                    $uploaded_files[] = ['filename' => $filename];
                }
            }
        }
    }
    
    $pdo->commit();
    
    // Send success response FIRST (before admin notifications)
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'uploaded_files' => count($uploaded_files),
        'email_sent' => false,
        'message' => 'Quote submitted successfully. We will contact you to schedule an in-person assessment.',
        'is_duplicate_customer' => true,
        'duplicate_match_type' => 'email',
        'crm_dashboard_url' => "https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}"
    ]);
    
    // Finish request to user
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Now try admin notification (should not affect user experience)
    try {
        // Use output buffering to catch any unexpected output
        ob_start();
        require_once __DIR__ . '/admin-notification.php';
        $admin_notification_sent = sendAdminNotification($quote_id);
        ob_end_clean(); // Discard any output
        error_log("Admin notification for quote $quote_id: " . ($admin_notification_sent ? 'sent' : 'failed'));
    } catch (Exception $e) {
        ob_end_clean(); // Clean up on error
        error_log("Failed to send admin notification for quote $quote_id: " . $e->getMessage());
    } catch (Error $e) {
        ob_end_clean(); // Clean up on fatal error
        error_log("Fatal error in admin notification for quote $quote_id: " . $e->getMessage());
    }

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