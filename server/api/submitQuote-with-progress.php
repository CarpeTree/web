<?php
// Quote submission with pre-uploaded files (progress system)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database-simple.php';

try {
    // Basic validation
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
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
    
    // Determine status based on uploaded files
    $uploaded_file_ids = [];
    if (!empty($_POST['uploadedFileIds'])) {
        $decoded = json_decode($_POST['uploadedFileIds'], true);
        if (is_array($decoded)) {
            $uploaded_file_ids = $decoded;
        }
    }
    
    $initial_status = !empty($uploaded_file_ids) ? 'ai_processing' : 'submitted';
    
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
        $initial_status
    ]);
    
    $quote_id = $pdo->lastInsertId();
    
    // Process uploaded files from session
    $processed_files = 0;
    if (!empty($uploaded_file_ids)) {
        session_start();
        $uploaded_files = $_SESSION['uploaded_files'] ?? [];
        
        // Create final upload directory
        $final_dir = __DIR__ . '/../uploads/quote_' . $quote_id;
        if (!is_dir($final_dir)) {
            mkdir($final_dir, 0755, true);
        }
        
        foreach ($uploaded_file_ids as $file_id) {
            if (isset($uploaded_files[$file_id])) {
                $file_info = $uploaded_files[$file_id];
                
                // Move from temp to final location
                $final_filename = 'file_' . $processed_files . '_' . time() . '_' . basename($file_info['original_name']);
                $final_path = $final_dir . '/' . $final_filename;
                
                if (rename($file_info['temp_path'], $final_path)) {
                    // Store in database if uploaded_files table exists
                    try {
                        $file_stmt = $pdo->prepare("
                            INSERT INTO uploaded_files (
                                quote_id, original_filename, stored_filename, 
                                file_path, file_size, mime_type, uploaded_at
                            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $file_stmt->execute([
                            $quote_id,
                            $file_info['original_name'],
                            $final_filename,
                            $final_path,
                            $file_info['file_size'],
                            $file_info['mime_type']
                        ]);
                    } catch (PDOException $e) {
                        // Table might not exist yet - that's okay
                        error_log("Could not store file in database: " . $e->getMessage());
                    }
                    
                    $processed_files++;
                    
                    // Remove from session
                    unset($_SESSION['uploaded_files'][$file_id]);
                }
            }
        }
    }
    
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
                " . ($processed_files > 0 ? "<p><strong>Files uploaded:</strong> $processed_files photo(s)/video(s)</p>" : "") . "
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
        error_log("Customer email failed: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success immediately
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'files_processed' => $processed_files,
        'customer_email_sent' => $customer_email_sent,
        'message' => 'Quote submitted successfully!'
    ]);
    
    // Background processing (after response sent)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Quick AI assessment
    try {
        require_once __DIR__ . '/quick-ai-assessment.php';
        $quick_assessment = quickAIAssessment($quote_id);
        error_log("Quick assessment for quote $quote_id: Category={$quick_assessment['category']}, Score={$quick_assessment['sufficiency_score']}/100");
    } catch (Exception $e) {
        error_log("Quick assessment failed for quote $quote_id: " . $e->getMessage());
    }
    
    // Send admin notification
    try {
        $admin_subject = "New Quote #$quote_id - " . ($_POST['name'] ?? 'Customer');
        $admin_message = "
            New quote submitted with progressive upload:
            
            Quote ID: #$quote_id
            Customer: " . ($_POST['name'] ?? 'Not provided') . "
            Email: " . $_POST['email'] . "
            Phone: " . ($_POST['phone'] ?? 'Not provided') . "
            Address: " . ($_POST['address'] ?? 'Not provided') . "
            Services: " . implode(', ', $selected_services) . "
            Files: $processed_files uploaded
            Notes: " . ($_POST['notes'] ?? 'None') . "
            
            Review at: https://carpetree.com/admin-dashboard.html
        ";
        
        $admin_headers = "From: Carpe Tree'em System <system@carpetree.com>\r\n";
        mail('phil.bajenski@gmail.com', $admin_subject, $admin_message, $admin_headers);
    } catch (Exception $e) {
        error_log("Admin notification failed: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 