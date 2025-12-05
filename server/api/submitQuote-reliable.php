<?php
// Ultra-reliable quote submission with comprehensive error handling
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
        'debug' => 'Only POST requests are accepted'
    ]);
    exit();
}

// Error logging function
function logError($message, $context = []) {
    $log = date('Y-m-d H:i:s') . " [ERROR] " . $message;
    if (!empty($context)) {
        $log .= " Context: " . json_encode($context);
    }
    error_log($log);
}

try {
    // Basic validation
    if (empty($_POST['email'])) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Try database connection with fallback methods
    $pdo = null;
    $connection_methods = [
        'config.php',
        'database-simple.php',
        'direct connection'
    ];
    
    foreach ($connection_methods as $method) {
        try {
            switch ($method) {
                case 'config.php':
                    if (file_exists(__DIR__ . '/../config/config.php')) {
                        require_once __DIR__ . '/../config/config.php';
                        $pdo = getDatabaseConnection();
                    }
                    break;
                    
                case 'database-simple.php':
                    if (file_exists(__DIR__ . '/../config/database-simple.php')) {
                        require_once __DIR__ . '/../config/database-simple.php';
                        // $pdo should be available from this file
                    }
                    break;
                    
                case 'direct connection':
                    // Try direct connection as last resort
                    $db_config = [
                        'host' => getenv('DB_HOST') ?: 'localhost',
                        'name' => getenv('DB_NAME') ?: '',
                        'user' => getenv('DB_USER') ?: '',
                        'pass' => getenv('DB_PASS') ?: ''
                    ];
                    
                    if (!empty($db_config['name']) && !empty($db_config['user'])) {
                        $pdo = new PDO(
                            "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
                            $db_config['user'],
                            $db_config['pass'],
                            [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                            ]
                        );
                    }
                    break;
            }
            
            if ($pdo) {
                // Test the connection
                $pdo->query("SELECT 1");
                break;
            }
            
        } catch (Exception $e) {
            logError("Database connection failed with method: $method", ['error' => $e->getMessage()]);
            continue;
        }
    }
    
    if (!$pdo) {
        throw new Exception('Unable to establish database connection. Please try again later.');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Minimal customer insertion/update
    try {
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
            $_POST['name'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null
        ]);
        
        // Get customer ID
        $customer_id = $pdo->lastInsertId();
        if (!$customer_id) {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            $customer = $stmt->fetch();
            $customer_id = $customer['id'];
        }

        // Capture optional geolocation/IP for distance calculations
        $geo_lat = null;
        $geo_lng = null;
        $geo_accuracy = null;
        if (isset($_POST['gpsLat']) && isset($_POST['gpsLng'])) {
            $geo_lat = (float) $_POST['gpsLat'];
            $geo_lng = (float) $_POST['gpsLng'];
            $geo_accuracy = isset($_POST['gpsAccuracy']) ? (float) $_POST['gpsAccuracy'] : null;
        } elseif (isset($_POST['geo_latitude']) && isset($_POST['geo_longitude'])) {
            $geo_lat = (float) $_POST['geo_latitude'];
            $geo_lng = (float) $_POST['geo_longitude'];
            $geo_accuracy = isset($_POST['geo_accuracy']) ? (float) $_POST['geo_accuracy'] : null;
        } elseif (isset($_POST['exifLat']) && isset($_POST['exifLng'])) {
            $geo_lat = (float) $_POST['exifLat'];
            $geo_lng = (float) $_POST['exifLng'];
        }
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            if ($geo_lat && $geo_lng) {
                $u = $pdo->prepare("UPDATE customers SET geo_latitude = ?, geo_longitude = ?, geo_accuracy = COALESCE(?, geo_accuracy), ip_address = COALESCE(?, ip_address), updated_at = NOW() WHERE id = ?");
                $u->execute([$geo_lat, $geo_lng, $geo_accuracy, $ip_address, $customer_id]);
            } elseif ($ip_address) {
                $u = $pdo->prepare("UPDATE customers SET ip_address = COALESCE(?, ip_address), updated_at = NOW() WHERE id = ?");
                $u->execute([$ip_address, $customer_id]);
            }
        } catch (Exception $geoErr) {
            // Columns may not exist in some environments; log and proceed
            logError('Optional geo/ip update failed', ['error' => $geoErr->getMessage()]);
        }
        
    } catch (Exception $e) {
        logError("Customer insertion failed", ['error' => $e->getMessage(), 'email' => $_POST['email']]);
        throw new Exception('Failed to process customer information');
    }
    
    // Create quote
    try {
        $selected_services = [];
        if (isset($_POST['selectedServices'])) {
            $selected_services = json_decode($_POST['selectedServices'], true) ?: [];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO quotes (
                customer_id, selected_services, notes, quote_status, 
                referral_source, referrer_name, newsletter_opt_in, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $customer_id,
            json_encode($selected_services),
            $_POST['notes'] ?? null,
            'submitted',
            $_POST['referralSource'] ?? null,
            $_POST['referrerName'] ?? null,
            isset($_POST['newsletterOptIn']) && $_POST['newsletterOptIn'] === 'true' ? 1 : 0
        ]);
        
        $quote_id = $pdo->lastInsertId();
        
    } catch (Exception $e) {
        logError("Quote insertion failed", ['error' => $e->getMessage(), 'customer_id' => $customer_id]);
        throw new Exception('Failed to create quote');
    }
    
    // Handle file uploads if any
    $file_count = 0;
    if (!empty($_FILES) && isset($_FILES['files'])) {
        try {
            // Use per-quote folder structure expected by dashboards: /server/uploads/quote_{id}/
            $base_upload_dir = __DIR__ . '/../../uploads';
            if (!is_dir($base_upload_dir)) {
                mkdir($base_upload_dir, 0755, true);
            }
            $upload_dir = $base_upload_dir . '/quote_' . $quote_id;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $files = $_FILES['files'];
            if (is_array($files['error'])) {
                for ($i = 0; $i < count($files['error']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($files['name'][$i]);
                        $file_path = $upload_dir . '/' . $filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO media (quote_id, filename, original_filename, file_path, file_size, mime_type, uploaded_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            
                            $stmt->execute([
                                $quote_id,
                                $filename,
                                $files['name'][$i],
                                'uploads/quote_' . $quote_id . '/' . $filename,
                                $files['size'][$i],
                                $files['type'][$i]
                            ]);
                            
                            $file_count++;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            logError("File upload failed", ['error' => $e->getMessage()]);
            // Don't fail the entire submission for file upload issues
        }
    }
    
    // Update quote status based on files
    if ($file_count > 0) {
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
        $stmt->execute([$quote_id]);
    }
    
    // Commit transaction
    $pdo->commit();

    // CRITICAL: Send IMMEDIATE admin email notification BEFORE any AI processing
    // This ensures customer data reaches CRM even if AI fails
    try {
        require_once __DIR__ . '/../utils/mailer.php';
        
        $admin_email = $_ENV['ADMIN_EMAIL'] ?? 'phil.bajenski@gmail.com';
        $customer_name = $_POST['name'] ?? 'Unknown';
        $customer_email = $_POST['email'];
        $customer_phone = $_POST['phone'] ?? 'Not provided';
        $customer_address = $_POST['address'] ?? 'Not provided';
        $customer_notes = $_POST['notes'] ?? '';
        
        // Build immediate notification email (no AI data yet)
        $immediate_subject = "NEW QUOTE #{$quote_id} - {$customer_name} - IMMEDIATE NOTIFICATION";
        
        $immediate_html = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #2D5A27, #4a7c59); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .info-row { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
            .label { font-weight: bold; color: #2D5A27; }
            .urgent { background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 8px; }
            .footer { background: #2D5A27; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; }
            a { color: #2D5A27; }
        </style></head>
        <body>
            <div class='header'>
                <h1>New Quote Submitted - #{$quote_id}</h1>
                <p>Immediate notification - AI analysis will follow</p>
            </div>
            <div class='content'>
                <div class='urgent'>
                    <strong>IMMEDIATE NOTIFICATION</strong><br>
                    This email was sent immediately upon form submission. AI analysis is processing separately and you will receive a follow-up email with results.
                </div>
                
                <h2>Customer Information</h2>
                <div class='info-row'><span class='label'>Name:</span> " . htmlspecialchars($customer_name) . "</div>
                <div class='info-row'><span class='label'>Email:</span> <a href='mailto:" . htmlspecialchars($customer_email) . "'>" . htmlspecialchars($customer_email) . "</a></div>
                <div class='info-row'><span class='label'>Phone:</span> <a href='tel:" . htmlspecialchars($customer_phone) . "'>" . htmlspecialchars($customer_phone) . "</a></div>
                <div class='info-row'><span class='label'>Address:</span> " . htmlspecialchars($customer_address) . "</div>
                
                <h2>Quote Details</h2>
                <div class='info-row'><span class='label'>Quote ID:</span> #{$quote_id}</div>
                <div class='info-row'><span class='label'>Files Uploaded:</span> {$file_count}</div>
                <div class='info-row'><span class='label'>Submitted:</span> " . date('Y-m-d H:i:s') . "</div>
                " . (!empty($customer_notes) ? "<div class='info-row'><span class='label'>Notes:</span> " . htmlspecialchars($customer_notes) . "</div>" : "") . "
                
                <h2>Quick Actions</h2>
                <p>
                    <a href='https://carpetree.com/admin-dashboard.html?quote_id={$quote_id}' style='display:inline-block; background:#2D5A27; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin:5px;'>View in Dashboard</a>
                    <a href='https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}' style='display:inline-block; background:#4a7c59; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin:5px;'>View Customer</a>
                </p>
            </div>
            <div class='footer'>
                <p>Carpe Tree'em - Professional Tree Care Services<br>778-655-3741 | sapport@carpetree.com</p>
            </div>
        </body>
        </html>";
        
        // Send immediate notification using direct method (no template dependency)
        sendEmailDirect($admin_email, $immediate_subject, $immediate_html, $quote_id);
        
        error_log("IMMEDIATE admin notification sent for quote #{$quote_id} to {$admin_email}");
        
    } catch (Throwable $emailErr) {
        // Log but don't fail - the quote was already saved
        logError('Immediate admin email failed (quote still saved)', [
            'quote_id' => $quote_id,
            'error' => $emailErr->getMessage()
        ]);
    }

    // Auto-trigger AI after successful submission (non-blocking)
    // Wrapped in separate try-catch so email is guaranteed even if AI fails
    if ($file_count > 0) {
        try {
            // Reflect batch processing state immediately
            $pdo->prepare("UPDATE quotes SET quote_status = 'multi_ai_processing' WHERE id = ?")->execute([$quote_id]);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'carpetree.com';
            $triggerUrl = $scheme . '://' . $host . '/server/api/trigger-all-analyses.php?quote_id=' . urlencode($quote_id);
            $ch = curl_init($triggerUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_CUSTOMREQUEST => 'GET'
            ]);
            curl_exec($ch);
            curl_close($ch);

            // Also auto-run context assessor in background
            $assessUrl = $scheme . '://' . $host . '/server/api/assess-context.php?quote_id=' . urlencode($quote_id);
            $ch2 = curl_init($assessUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_CUSTOMREQUEST => 'GET'
            ]);
            curl_exec($ch2);
            curl_close($ch2);
        } catch (Throwable $t) {
            logError('Auto AI trigger failed', ['quote_id' => $quote_id, 'error' => $t->getMessage()]);
        }
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'message' => 'Quote submitted successfully',
        'files_uploaded' => $file_count,
        'preflight_check' => [
            'sufficient' => true,
            'confidence' => 'high'
        ],
        'crm_dashboard_url' => "https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if it exists
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    logError("Quote submission failed", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>