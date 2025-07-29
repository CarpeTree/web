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
    $files_uploaded = false;
    if (!empty($_FILES) && isset($_FILES['files'])) {
        if (is_array($_FILES['files']['error'])) {
            $files_uploaded = $_FILES['files']['error'][0] !== UPLOAD_ERR_NO_FILE;
        } else {
            $files_uploaded = $_FILES['files']['error'] !== UPLOAD_ERR_NO_FILE;
        }
    }
    
    // Start database transaction
    $pdo->beginTransaction();
    
    // Enhanced location and IP tracking
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    // Get geolocation data if provided
    $geo_latitude = $_POST['geo_latitude'] ?? null;
    $geo_longitude = $_POST['geo_longitude'] ?? null;
    $geo_accuracy = $_POST['geo_accuracy'] ?? null;
    
    // Also check for GPS data from the location button
    if (!$geo_latitude && isset($_POST['gpsLat'])) {
        $geo_latitude = $_POST['gpsLat'];
        $geo_longitude = $_POST['gpsLng'];
        $geo_accuracy = $_POST['gpsAccuracy'] ?? null;
    }
    
    // Log comprehensive submission data
    $location_info = '';
    if ($geo_latitude && $geo_longitude) {
        $accuracy_text = $geo_accuracy ? " (±${geo_accuracy}m)" : '';
        $location_info = " | GPS: $geo_latitude,$geo_longitude$accuracy_text";
    }
    error_log("Quote submission from IP: $ip_address | User Agent: $user_agent | Address: " . ($_POST['address'] ?? 'Not provided') . $location_info);
    
    // Insert customer with enhanced location data
    $stmt = $pdo->prepare("
        INSERT INTO customers (
            name, email, phone, address, referral_source, referrer_name, newsletter_opt_in, 
            ip_address, user_agent, geo_latitude, geo_longitude, geo_accuracy, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['name'] ?? '',
        $_POST['email'],
        $_POST['phone'] ?? null,
        $_POST['address'] ?? null,
        $_POST['referral_source'] ?? null,
        $_POST['referrer_name'] ?? null,
        isset($_POST['newsletter_opt_in']) ? 1 : 0,
        $ip_address,
        $user_agent,
        $geo_latitude ? (float)$geo_latitude : null,
        $geo_longitude ? (float)$geo_longitude : null,
        $geo_accuracy ? (float)$geo_accuracy : null,
        $timestamp
    ]);
    
    $customer_id = $pdo->lastInsertId();
    
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
            WHERE REGEXP_REPLACE(phone, '[^0-9]', '') = ? OR phone = ?
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
    
    // If still not found, check by address only (same property, different person?)
    if (!$customer && !empty($_POST['address'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM customers 
            WHERE LOWER(TRIM(address)) = LOWER(TRIM(?))
        ");
        $stmt->execute([$_POST['address']]);
        $customer = $stmt->fetch();
        if ($customer) {
            $duplicate_match_type = 'address';
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
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            $_POST['howDidYouHear'] ?? null,
            $_POST['referrerName'] ?? null,
            $customer_id
        ]);
        
        // Log duplicate customer detection with match type
        if ($is_duplicate) {
            error_log("DUPLICATE CUSTOMER DETECTED: Customer ID $customer_id ({$_POST['email']}) has submitted before - matched by: $duplicate_match_type");
        }
    } else {
        // Create new customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (email, name, phone, address, referral_source, referrer_name) 
            VALUES (?, ?, ?, ?, ?, ?)
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
        $is_duplicate = false;
    }
    
    // Parse selected services
    $selected_services = [];
    if (!empty($_POST['selectedServices'])) {
        $services_json = $_POST['selectedServices'];
        $selected_services = json_decode($services_json, true) ?: [];
    }
    
    // Combine notes with other service details if provided
    $notes = $_POST['notes'] ?? '';
    if (!empty($_POST['otherServiceDetails'])) {
        $other_details = $_POST['otherServiceDetails'];
        $notes = $notes ? $notes . "\n\nOther Service Details:\n" . $other_details : "Other Service Details:\n" . $other_details;
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
        $notes,
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
            $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted' WHERE id = ?");
            $stmt->execute([$quote_id]);
        }
    } else {
        // If no files were uploaded, set status to submitted
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted' WHERE id = ?");
        $stmt->execute([$quote_id]);
    }
    
    // Ensure compatibility view for legacy code (uploaded_files)
    try {
        $pdo->exec("CREATE OR REPLACE VIEW uploaded_files AS 
            SELECT 
                id,
                quote_id,
                filename,
                original_filename,
                file_path,
                file_size,
                mime_type,
                uploaded_at,
                NULL AS file_hash,
                exif_data
            FROM media");
    } catch (Exception $e) {
        // log but don't block submission
        error_log('Failed to create uploaded_files view: ' . $e->getMessage());
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
        'message' => $message,
        'is_duplicate_customer' => $is_duplicate,
        'duplicate_match_type' => $duplicate_match_type,
        'crm_dashboard_url' => "https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}"
    ]);
    
    // Send admin notification asynchronously (after response sent)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Send admin notification without blocking user
    try {
        require_once __DIR__ . '/admin-notification.php';
        $admin_notification_sent = sendAdminNotification($quote_id);
        error_log("Admin notification for quote $quote_id: " . ($admin_notification_sent ? 'sent' : 'failed'));
    } catch (Exception $e) {
        error_log("Failed to send admin notification for quote $quote_id: " . $e->getMessage());
    }
    
    // Trigger AI processing for quotes with media files (asynchronous)
    if (!empty($uploaded_files)) {
        try {
            // Update status to indicate AI processing has started
            $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
            $stmt->execute([$quote_id]);
            
            // Trigger context assessment first
            require_once __DIR__ . '/../utils/context-assessor.php';
            $context_assessment = ContextAssessor::assessSubmissionContext($quote_id);
            error_log("Context assessment for quote $quote_id: " . json_encode($context_assessment));
            
            // Trigger AI analysis
            $ai_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                     '://' . $_SERVER['HTTP_HOST'] . '/server/api/simple-ai-analysis.php';
            
            $post_data = http_build_query(['quote_id' => $quote_id, 'triggered_by' => 'submitQuote']);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $post_data,
                    'timeout' => 2
                ]
            ]);
            
            // Non-blocking call
            @file_get_contents($ai_url, false, $context);
            
        } catch (Exception $e) {
            error_log("Failed to trigger AI processing for quote $quote_id: " . $e->getMessage());
        }
    } else {
        // Even without files, assess the text-based context
        try {
            require_once __DIR__ . '/../utils/context-assessor.php';
            $context_assessment = ContextAssessor::assessSubmissionContext($quote_id);
            error_log("Text-only context assessment for quote $quote_id: " . json_encode($context_assessment));
        } catch (Exception $e) {
            error_log("Failed context assessment for quote $quote_id: " . $e->getMessage());
        }
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