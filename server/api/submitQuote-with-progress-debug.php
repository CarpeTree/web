<?php
// Debug version of quote submission with pre-uploaded files
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function debugLog($message) {
    error_log("[SUBMIT-DEBUG] " . $message);
    echo "<!-- DEBUG: " . htmlspecialchars($message) . " -->";
}

debugLog("Starting submission process");
debugLog("POST data received: " . json_encode($_POST, JSON_PARTIAL_OUTPUT_ON_ERROR));

require_once __DIR__ . '/../config/database-simple.php';

try {
    debugLog("Database connection established");
    
    // Basic validation
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    debugLog("Email validation passed: " . $_POST['email']);
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    debugLog("Email format validation passed");
    
    // Additional field validation to catch pattern issues
    $fields_to_check = ['name', 'phone', 'address', 'referralSource', 'referrerName', 'notes'];
    foreach ($fields_to_check as $field) {
        $value = $_POST[$field] ?? '';
        debugLog("Field '$field' value: '" . $value . "' (length: " . strlen($value) . ")");
        
        // Check for any non-printable characters that might cause pattern issues
        if (preg_match('/[^\x20-\x7E\r\n\t]/', $value)) {
            debugLog("WARNING: Field '$field' contains non-printable characters");
        }
    }
    
    // Start transaction
    $pdo->beginTransaction();
    debugLog("Transaction started");
    
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
    debugLog("Customer insert/update completed");
    
    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }
    debugLog("Customer ID: " . $customer_id);
    
    // Parse services with detailed debugging
    $selected_services = [];
    debugLog("Raw selectedServices: " . ($_POST['selectedServices'] ?? 'NOT SET'));
    
    if (!empty($_POST['selectedServices'])) {
        debugLog("Attempting to decode selectedServices JSON");
        $decoded = json_decode($_POST['selectedServices'], true);
        debugLog("JSON decode result: " . json_encode($decoded, JSON_PARTIAL_OUTPUT_ON_ERROR));
        debugLog("JSON last error: " . json_last_error_msg());
        
        if (is_array($decoded)) {
            $selected_services = $decoded;
            debugLog("Selected services parsed successfully: " . count($selected_services) . " services");
        } else {
            debugLog("WARNING: selectedServices decode failed or not array");
        }
    } else {
        debugLog("No selectedServices provided");
    }
    
    // Parse uploaded file IDs with detailed debugging
    $uploaded_file_ids = [];
    debugLog("Raw uploadedFileIds: " . ($_POST['uploadedFileIds'] ?? 'NOT SET'));
    
    if (!empty($_POST['uploadedFileIds'])) {
        debugLog("Attempting to decode uploadedFileIds JSON");
        $decoded = json_decode($_POST['uploadedFileIds'], true);
        debugLog("JSON decode result: " . json_encode($decoded, JSON_PARTIAL_OUTPUT_ON_ERROR));
        debugLog("JSON last error: " . json_last_error_msg());
        
        if (is_array($decoded)) {
            $uploaded_file_ids = $decoded;
            debugLog("File IDs parsed successfully: " . count($uploaded_file_ids) . " files");
        } else {
            debugLog("WARNING: uploadedFileIds decode failed or not array");
        }
    } else {
        debugLog("No uploadedFileIds provided");
    }
    
    $initial_status = !empty($uploaded_file_ids) ? 'ai_processing' : 'submitted';
    debugLog("Initial status: " . $initial_status);
    
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
    
    $services_json = json_encode($selected_services);
    debugLog("Services JSON for database: " . $services_json);
    
    $stmt->execute([
        $customer_id,
        $services_json,
        $_POST['notes'] ?? '',
        $initial_status
    ]);
    
    $quote_id = $pdo->lastInsertId();
    debugLog("Quote created with ID: " . $quote_id);
    
    // Process uploaded files from session
    $processed_files = 0;
    if (!empty($uploaded_file_ids)) {
        debugLog("Processing uploaded files from session");
        session_start();
        $uploaded_files = $_SESSION['uploaded_files'] ?? [];
        debugLog("Session files available: " . count($uploaded_files));
        debugLog("Session file keys: " . json_encode(array_keys($uploaded_files)));
        
        // Create final upload directory
        $final_dir = __DIR__ . '/../uploads/quote_' . $quote_id;
        if (!is_dir($final_dir)) {
            mkdir($final_dir, 0755, true);
            debugLog("Created final directory: " . $final_dir);
        }
        
        foreach ($uploaded_file_ids as $file_id) {
            debugLog("Processing file ID: " . $file_id);
            if (isset($uploaded_files[$file_id])) {
                $file_info = $uploaded_files[$file_id];
                debugLog("File info found: " . json_encode($file_info, JSON_PARTIAL_OUTPUT_ON_ERROR));
                
                // Move from temp to final location
                $final_filename = 'file_' . $processed_files . '_' . time() . '_' . basename($file_info['original_name']);
                $final_path = $final_dir . '/' . $final_filename;
                
                if (rename($file_info['temp_path'], $final_path)) {
                    debugLog("File moved successfully to: " . $final_path);
                    
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
                        debugLog("File stored in database");
                    } catch (PDOException $e) {
                        debugLog("Could not store file in database: " . $e->getMessage());
                    }
                    
                    $processed_files++;
                    
                    // Remove from session
                    unset($_SESSION['uploaded_files'][$file_id]);
                    debugLog("File removed from session");
                } else {
                    debugLog("ERROR: Failed to move file from " . $file_info['temp_path'] . " to " . $final_path);
                }
            } else {
                debugLog("WARNING: File ID not found in session: " . $file_id);
            }
        }
    }
    
    debugLog("Processed files count: " . $processed_files);
    
    // Send customer confirmation email
    $customer_email_sent = false;
    try {
        debugLog("Sending customer confirmation email");
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
        debugLog("Customer email sent: " . ($customer_email_sent ? 'success' : 'failed'));
    } catch (Exception $e) {
        debugLog("Customer email failed: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    debugLog("Transaction committed successfully");
    
    // Return success immediately
    $response = [
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'files_processed' => $processed_files,
        'customer_email_sent' => $customer_email_sent,
        'message' => 'Quote submitted successfully!',
        'debug_info' => [
            'selected_services_count' => count($selected_services),
            'uploaded_file_ids_count' => count($uploaded_file_ids),
            'processed_files' => $processed_files
        ]
    ];
    
    debugLog("Returning success response: " . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR));
    echo json_encode($response);
    
} catch (Exception $e) {
    debugLog("EXCEPTION CAUGHT: " . $e->getMessage());
    debugLog("Exception trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
        debugLog("Transaction rolled back");
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'post_data' => $_POST,
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
}
?> 