<?php
// Simple working quote submission - no complex features
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Basic validation
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Connect to database
    require_once '../config/database-simple.php';
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert or update customer
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, created_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        name = COALESCE(VALUES(name), name),
        phone = COALESCE(VALUES(phone), phone),
        address = COALESCE(VALUES(address), address),
        updated_at = NOW()
    ");
    
    $stmt->execute([
        $_POST['email'],
        $_POST['name'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['address'] ?? ''
    ]);
    
    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }
    
    // Parse services
    $selected_services = [];
    if (!empty($_POST['selectedServices'])) {
        $decoded = json_decode($_POST['selectedServices'], true);
        if (is_array($decoded)) {
            $selected_services = $decoded;
        }
    }
    
    // Insert quote
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, 
            selected_services, 
            notes, 
            quote_status, 
            quote_created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $customer_id,
        json_encode($selected_services),
        $_POST['notes'] ?? '',
        'submitted'
    ]);
    
    $quote_id = $pdo->lastInsertId();
    
    // Handle file uploads (simplified)
    $file_count = 0;
    if (!empty($_FILES['files'])) {
        $upload_dir = '../uploads/quote_' . $quote_id;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $files = $_FILES['files'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = 'upload_' . $i . '_' . time() . '_' . basename($files['name'][$i]);
                    $file_path = $upload_dir . '/' . $file_name;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                        $file_count++;
                        
                        // Log file in database (using correct media table schema)
                        $file_type = strpos($files['type'][$i], 'image/') === 0 ? 'image' : 
                                   (strpos($files['type'][$i], 'video/') === 0 ? 'video' : 'audio');
                        
                        $file_stmt = $pdo->prepare("
                            INSERT INTO media (quote_id, original_filename, filename, file_path, file_size, mime_type, file_type, uploaded_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $file_stmt->execute([
                            $quote_id,
                            $files['name'][$i],
                            $file_name,
                            $file_path,
                            $files['size'][$i],
                            $files['type'][$i],
                            $file_type
                        ]);
                    }
                }
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Send customer confirmation email
    $customer_email_sent = false;
    try {
        $to = $_POST['email'];
        $subject = "Quote Request Received - Carpe Tree'em";
        $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Thank you for your quote request!</h2>
                <p>Hi " . htmlspecialchars($_POST['name'] ?? 'there') . ",</p>
                <p>We've received your tree service quote request and will get back to you within 24 hours.</p>
                <p><strong>Quote ID:</strong> #$quote_id</p>
                <p><strong>Services requested:</strong> " . implode(', ', $selected_services) . "</p>
                " . ($file_count > 0 ? "<p><strong>Files uploaded:</strong> $file_count photo(s)/video(s)</p>" : "") . "
                <p>If you have any questions, please call us at 778-655-3741.</p>
                <p>Best regards,<br>Carpe Tree'em Team</p>
            </body>
            </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Carpe Tree'em <quotes@carpetree.com>\r\n";
        
        $customer_email_sent = mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        // Email failed but quote was saved - that's okay
        error_log("Customer email failed: " . $e->getMessage());
    }
    
    // Send simple admin notification (async)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Return success immediately
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'files_uploaded' => $file_count,
        'customer_email_sent' => $customer_email_sent,
        'message' => 'Quote submitted successfully! We will contact you within 24 hours.'
    ]);
    
    // Send admin notification after response (non-blocking)
    try {
        $admin_subject = "New Quote #$quote_id - " . ($_POST['name'] ?? 'Customer');
        $admin_message = "
            New quote submitted:
            
            Quote ID: #$quote_id
            Customer: " . ($_POST['name'] ?? 'Not provided') . "
            Email: " . $_POST['email'] . "
            Phone: " . ($_POST['phone'] ?? 'Not provided') . "
            Address: " . ($_POST['address'] ?? 'Not provided') . "
            Services: " . implode(', ', $selected_services) . "
            Files: $file_count uploaded
            Notes: " . ($_POST['notes'] ?? 'None') . "
            
            Review at: https://carpetree.com/admin-dashboard.html
        ";
        
        $admin_headers = "From: Carpe Tree'em System <system@carpetree.com>\r\n";
        mail('sapport@carpetree.com', $admin_subject, $admin_message, $admin_headers);
    } catch (Exception $e) {
        error_log("Admin notification failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    // Rollback transaction if it exists
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Log error
    error_log("Quote submission error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'post_data' => $_POST,
            'files_data' => isset($_FILES) ? array_keys($_FILES) : []
        ]
    ]);
}
?> 