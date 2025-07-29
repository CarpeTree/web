<?php
// Debug version of submitQuote.php - logs every step
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);

function debugLog($message) {
    error_log("[MOBILE_DEBUG] " . $message);
    echo "<!-- DEBUG: $message -->\n";
    flush();
    ob_flush();
}

try {
    debugLog("=== MOBILE SUBMISSION DEBUG START ===");
    debugLog("Request method: " . $_SERVER['REQUEST_METHOD']);
    debugLog("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    debugLog("POST data size: " . count($_POST));
    debugLog("FILES data size: " . count($_FILES));
    
    // Basic validation
    debugLog("Starting validation...");
    
    if (empty($_POST['email'])) {
        debugLog("ERROR: No email provided");
        throw new Exception('Email is required');
    }
    debugLog("Email validated: " . $_POST['email']);
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        debugLog("ERROR: Invalid email format");
        throw new Exception('Invalid email format');
    }
    debugLog("Email format valid");
    
    // Database connection
    debugLog("Connecting to database...");
    require_once __DIR__ . '/../config/database-simple.php';
    debugLog("Database connected successfully");
    
    // Check files
    $files_uploaded = !empty($_FILES) && isset($_FILES['files']) && $_FILES['files']['error'][0] !== UPLOAD_ERR_NO_FILE;
    debugLog("Files uploaded: " . ($files_uploaded ? 'YES' : 'NO'));
    
    // Start transaction
    debugLog("Starting database transaction...");
    $pdo->beginTransaction();
    debugLog("Transaction started");
    
    // Check customer
    debugLog("Checking for existing customer...");
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        debugLog("Found existing customer: " . $customer['id']);
        $customer_id = $customer['id'];
        
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
        debugLog("Customer updated");
    } else {
        debugLog("Creating new customer...");
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
        debugLog("New customer created: " . $customer_id);
    }
    
    // Parse services
    debugLog("Parsing selected services...");
    $selected_services = [];
    if (!empty($_POST['selectedServices'])) {
        $services_json = $_POST['selectedServices'];
        $selected_services = json_decode($services_json, true) ?: [];
        debugLog("Services parsed: " . implode(', ', $selected_services));
    } else {
        debugLog("No services selected");
    }
    
    // Create quote
    debugLog("Creating quote record...");
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
    debugLog("Quote status will be: " . $initial_status);
    
    $stmt->execute([
        $customer_id,
        json_encode($selected_services),
        $_POST['notes'] ?? '',
        $initial_status
    ]);
    
    $quote_id = $pdo->lastInsertId();
    debugLog("Quote created with ID: " . $quote_id);
    
    // Handle file uploads
    $uploaded_files = [];
    if ($files_uploaded) {
        debugLog("Processing file uploads...");
        $upload_dir = __DIR__ . '/../uploads/quote_' . $quote_id;
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
            debugLog("Created upload directory: " . $upload_dir);
        }
        
        $files = $_FILES['files'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    debugLog("Processing file $i: " . $files['name'][$i]);
                    
                    $file_name = 'upload_' . $i . '_' . time() . '_' . basename($files['name'][$i]);
                    $file_path = $upload_dir . '/' . $file_name;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                        debugLog("File uploaded successfully: " . $file_name);
                        $uploaded_files[] = [
                            'filename' => $file_name,
                            'original_name' => $files['name'][$i],
                            'path' => $file_path,
                            'size' => $files['size'][$i]
                        ];
                    } else {
                        debugLog("ERROR: Failed to upload file: " . $files['name'][$i]);
                    }
                }
            }
        }
        debugLog("File processing complete. Uploaded: " . count($uploaded_files));
    }
    
    // Send customer confirmation (simple version)
    debugLog("Sending customer confirmation email...");
    $customer_email_sent = false;
    try {
        $to = $_POST['email'];
        $subject = "Quote Request Received - Carpe Tree'em";
        $message = "Thank you for your quote request! We'll get back to you within 24 hours.\n\nQuote ID: #$quote_id";
        $headers = "From: Carpe Tree'em <quotes@carpetree.com>";
        
        $customer_email_sent = mail($to, $subject, $message, $headers);
        debugLog("Customer email sent: " . ($customer_email_sent ? 'SUCCESS' : 'FAILED'));
    } catch (Exception $e) {
        debugLog("Customer email error: " . $e->getMessage());
    }
    
    // Commit transaction
    debugLog("Committing transaction...");
    $pdo->commit();
    debugLog("Transaction committed successfully");
    
    // Return response immediately
    debugLog("Preparing response...");
    $response = [
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'files_uploaded' => count($uploaded_files),
        'customer_email_sent' => $customer_email_sent,
        'message' => 'Quote submitted successfully!'
    ];
    
    debugLog("Sending response to client...");
    echo json_encode($response);
    
    // Finish request processing for client
    if (function_exists('fastcgi_finish_request')) {
        debugLog("Finishing request for client...");
        fastcgi_finish_request();
    }
    
    // Background processing
    debugLog("Starting background processing...");
    
    // Quick assessment
    try {
        debugLog("Running quick AI assessment...");
        // Don't include the file to avoid HTTP handling issues
        // Just log that it would run
        debugLog("Quick assessment would run for quote: " . $quote_id);
    } catch (Exception $e) {
        debugLog("Quick assessment error: " . $e->getMessage());
    }
    
    // Admin notification
    try {
        debugLog("Sending admin notification...");
        $admin_message = "New quote #$quote_id from " . $_POST['email'];
        mail('sapport@carpetree.com', 'New Quote Submitted', $admin_message, 'From: system@carpetree.com');
        debugLog("Admin notification sent");
    } catch (Exception $e) {
        debugLog("Admin notification error: " . $e->getMessage());
    }
    
    debugLog("=== MOBILE SUBMISSION DEBUG COMPLETE ===");
    
} catch (Exception $e) {
    debugLog("FATAL ERROR: " . $e->getMessage());
    debugLog("Error in file: " . $e->getFile() . " line " . $e->getLine());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        debugLog("Rolling back transaction...");
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_location' => $e->getFile() . ':' . $e->getLine()
    ]);
}
?> 