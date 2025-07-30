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
    
    // Enhanced duplicate customer detection by email, phone, name, AND address
    $customer = null;
    $is_duplicate = false;
    $duplicate_match_type = '';
    
    // First check by email
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $duplicate_match_type = 'email';
    }
    
    // If not found by email, check by phone (if provided)
    if (!$customer && !empty($_POST['phone'])) {
        $clean_phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '(', ''), ')', '') = ? OR phone = ?
        ");
        $stmt->execute([$clean_phone, $_POST['phone']]);
        $customer = $stmt->fetch();
        if ($customer) {
            $duplicate_match_type = 'phone';
        }
    }
    
    // If not found by email/phone, check by name AND address combination (if both provided)
    if (!$customer && !empty($_POST['name']) && !empty($_POST['address'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) 
            AND LOWER(TRIM(address)) = LOWER(TRIM(?))
        ");
        $stmt->execute([$_POST['name'], $_POST['address']]);
        $customer = $stmt->fetch();
        if ($customer) {
            $duplicate_match_type = 'name_and_address';
        }
    }
    
    if ($customer) {
        $customer_id = $customer['id'];
        
        // Check if this is a returning customer (has previous quotes)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $quote_count = $stmt->fetchColumn();
        $is_duplicate = $quote_count > 0;
        
        // Update existing customer
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = COALESCE(?, name), 
                phone = COALESCE(?, phone), 
                address = COALESCE(?, address), 
                referral_source = COALESCE(?, referral_source),
                referrer_name = COALESCE(?, referrer_name),
                newsletter_opt_in = COALESCE(?, newsletter_opt_in),
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            $_POST['howDidYouHear'] ?? null,
            $_POST['referrerName'] ?? null,
            isset($_POST['newsletter_opt_in']) ? ($_POST['newsletter_opt_in'] === 'true' ? 1 : 0) : null,
            $customer_id
        ]);
        
        // Log duplicate customer detection
        if ($is_duplicate) {
            error_log("DUPLICATE CUSTOMER DETECTED: Customer ID $customer_id ({$_POST['email']}) has submitted before - matched by: $duplicate_match_type");
        }
    } else {
        // Create new customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (email, name, phone, address, referral_source, referrer_name, newsletter_opt_in, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['email'],
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['howDidYouHear'] ?? null,
            $_POST['referrerName'] ?? null,
            isset($_POST['newsletter_opt_in']) ? ($_POST['newsletter_opt_in'] === 'true' ? 1 : 0) : 0
        ]);
        $customer_id = $pdo->lastInsertId();
        $is_duplicate = false;
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
        'pending_files' // Will update to ai_processing or submitted based on file uploads
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
    
    // Update quote status based on file uploads and trigger AI processing
    if ($file_count > 0) {
        // Files uploaded - set for AI processing
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
        $stmt->execute([$quote_id]);
        
        error_log("Quote $quote_id: $file_count files uploaded, triggering AI analysis");
    } else {
        // No files - just submitted
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted' WHERE id = ?");
        $stmt->execute([$quote_id]);
        
        error_log("Quote $quote_id: No files uploaded, status set to submitted");
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
        'is_duplicate_customer' => $is_duplicate,
        'duplicate_match_type' => $duplicate_match_type,
        'crm_dashboard_url' => "https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}",
        'message' => 'Quote submitted successfully! We will contact you within 24 hours.'
    ]);
    
    // Send admin notification asynchronously (after response sent)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Send enhanced admin notification with all data and CRM links
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
    
    // Trigger AI processing for quotes with media (asynchronous)
    if ($file_count > 0) {
        try {
            // Trigger AI analysis in background  
            $ai_url = 'https://carpetree.com/server/api/simple-ai-analysis.php?quote_id=' . $quote_id;
            
            // Use cURL to trigger AI processing asynchronously
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $ai_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 1, // Very short timeout - fire and forget
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_NOSIGNAL => 1
            ]);
            
            $result = curl_exec($curl);
            curl_close($curl);
            
            error_log("Triggered AI processing for quote $quote_id with $file_count files");
        } catch (Exception $e) {
            error_log("Failed to trigger AI processing for quote $quote_id: " . $e->getMessage());
        }
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