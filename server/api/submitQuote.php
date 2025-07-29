<?php
// Quick fix version - admin notifications made async
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/fileHandler.php';
require_once __DIR__ . '/../utils/mailer.php';

try {
    // Validate required fields
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if files were uploaded (make it optional)
    $files_uploaded = !empty($_FILES) && isset($_FILES['files']) && $_FILES['files']['error'][0] !== UPLOAD_ERR_NO_FILE;
    
    // Start database transaction
    $pdo->beginTransaction();
    
    // Check if customer exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        $customer_id = $customer['id'];
        // Update existing customer
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, phone = ?, address = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $customer_id
        ]);
    } else {
        // Create new customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (email, name, phone, address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['email'],
            $_POST['name'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? ''
        ]);
        $customer_id = $pdo->lastInsertId();
    }
    
    // Parse selected services
    $selected_services = [];
    if (!empty($_POST['selectedServices'])) {
        $services_json = $_POST['selectedServices'];
        $selected_services = json_decode($services_json, true) ?: [];
    }
    
    // Create quote
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, 
            selected_services, 
            notes, 
            quote_status, 
            quote_created_at,
            ai_analysis_complete
        ) VALUES (?, ?, ?, ?, NOW(), 0)
    ");
    
    $initial_status = $files_uploaded ? 'ai_processing' : 'submitted';
    
    $stmt->execute([
        $customer_id,
        json_encode($selected_services),
        $_POST['notes'] ?? '',
        $initial_status
    ]);
    
    $quote_id = $pdo->lastInsertId();
    
    // Process uploaded files if any
    $uploaded_files = [];
    if ($files_uploaded) {
        // Create upload directory with full path
        $uploads_base = dirname(dirname(__DIR__)) . '/uploads';
        if (!file_exists($uploads_base)) {
            mkdir($uploads_base, 0755, true);
        }
        
        $upload_dir = "$uploads_base/$quote_id";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Process uploaded files
        $files = $_FILES['files'];
        
        // Handle multiple files
        if (is_array($files['tmp_name'])) {
            for ($i = 0; $i < count($files['tmp_name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $result = processUploadedFile([
                        'tmp_name' => $files['tmp_name'][$i],
                        'name' => $files['name'][$i],
                        'size' => $files['size'][$i],
                        'type' => $files['type'][$i]
                    ], $quote_id, $upload_dir, $pdo);
                    
                    if ($result) {
                        $uploaded_files[] = $result;
                    }
                }
            }
        } else {
            // Single file
            if ($files['error'] === UPLOAD_ERR_OK) {
                $result = processUploadedFile($files, $quote_id, $upload_dir, $pdo);
                if ($result) {
                    $uploaded_files[] = $result;
                }
            }
        }
        
        // Update quote status based on whether files were uploaded
        if (!empty($uploaded_files)) {
            $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
            $stmt->execute([$quote_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted_no_files' WHERE id = ?");
            $stmt->execute([$quote_id]);
        }
    } else {
        // If no files were uploaded, set status to submitted_no_files
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted_no_files' WHERE id = ?");
        $stmt->execute([$quote_id]);
    }
    
    // Commit transaction FIRST
    $pdo->commit();
    
    // Send immediate confirmation email (quick)
    $email_sent = false;
    try {
        $email_sent = sendEmail(
            $_POST['email'],
            'Quote Submission Received - Carpe Tree\'em',
            'quote_confirmation',
            [
                'customer_name' => $_POST['name'] ?? 'Customer',
                'quote_id' => $quote_id,
                'services' => $selected_services,
                'files_count' => count($uploaded_files),
                'has_files' => !empty($uploaded_files)
            ]
        );
    } catch (Exception $e) {
        error_log("Quick confirmation email failed: " . $e->getMessage());
    }
    
    // Determine message
    if (!empty($uploaded_files)) {
        $message = 'Quote submitted successfully. AI analysis will begin shortly.';
    } else {
        $message = 'Quote submitted successfully. We will contact you to schedule an in-person assessment.';
    }
    
    // Return success response IMMEDIATELY
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'uploaded_files' => count($uploaded_files),
        'email_sent' => $email_sent,
        'message' => $message
    ]);
    
    // Send admin notification asynchronously (after response sent)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Now send simple admin notification without blocking user
    try {
        require_once __DIR__ . '/admin-notification-simple.php';
        $admin_notification_sent = sendSimpleAdminAlert($quote_id);
        error_log("Simple admin notification for quote $quote_id: " . ($admin_notification_sent ? 'sent' : 'failed'));
    } catch (Exception $e) {
        error_log("Failed to send admin notification for quote $quote_id: " . $e->getMessage());
    }
    
    // Trigger quick AI assessment for all quotes (instant)
    try {
        require_once __DIR__ . '/quick-ai-assessment.php';
        $quick_assessment = quickAIAssessment($quote_id);
        error_log("Quick assessment for quote $quote_id: Category={$quick_assessment['category']}, Score={$quick_assessment['sufficiency_score']}/100");
    } catch (Exception $e) {
        error_log("Quick assessment failed for quote $quote_id: " . $e->getMessage());
    }
    
    // Trigger full AI processing asynchronously if files were uploaded
    if (!empty($uploaded_files)) {
        $ai_script = __DIR__ . '/aiQuote.php';
        $command = "cd " . dirname(__DIR__) . " && php api/aiQuote.php $quote_id > /dev/null 2>&1 &";
        exec($command);
    }
    
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