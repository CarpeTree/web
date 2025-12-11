<?php
// Ultra-reliable quote submission with comprehensive error handling
header('Content-Type: application/json');
// CORS not needed beyond same-origin; remove permissive wildcard

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

// Lightweight rate-limit: require a simple captcha after 3 attempts
session_start();
if (!isset($_SESSION['quote_attempts'])) {
    $_SESSION['quote_attempts'] = 0;
}
$_SESSION['quote_attempts'] = (int)$_SESSION['quote_attempts'] + 1;

function issueCaptchaChallenge($message = 'Please solve the captcha to continue') {
    $a = rand(2, 9);
    $b = rand(2, 9);
    $_SESSION['quote_captcha_answer'] = $a + $b;
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message,
        'captcha_required' => true,
        'captcha_question' => "What is {$a} + {$b}?"
    ]);
    exit();
}

if ($_SESSION['quote_attempts'] > 3) {
    $captcha_answer = $_POST['captcha_answer'] ?? $_POST['captchaAnswer'] ?? null;
    $expected = $_SESSION['quote_captcha_answer'] ?? null;
    if (!$captcha_answer || $expected === null || (int)$captcha_answer !== (int)$expected) {
        issueCaptchaChallenge();
    }
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
        
        // Store referral info in notes if provided (columns may not exist)
        $notes_with_meta = $_POST['notes'] ?? '';
        $referral_source = $_POST['referralSource'] ?? '';
        $referrer_name = $_POST['referrerName'] ?? '';
        if ($referral_source || $referrer_name) {
            $notes_with_meta .= "\n\n--- Referral Info ---\nSource: {$referral_source}" . ($referrer_name ? "\nReferred by: {$referrer_name}" : "");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO quotes (
                customer_id, selected_services, notes, quote_status, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $customer_id,
            json_encode($selected_services),
            trim($notes_with_meta) ?: null,
            'submitted'
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
        // Ensure config is loaded for SMTP settings
        if (!isset($SMTP_HOST) && file_exists(__DIR__ . '/../config/config.php')) {
            require_once __DIR__ . '/../config/config.php';
        }
        require_once __DIR__ . '/../utils/mailer.php';
        
        $admin_email = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: 'phil.bajenski@gmail.com';
        $customer_name = $_POST['name'] ?? 'Unknown';
        $customer_email = $_POST['email'];
        $customer_phone = $_POST['phone'] ?? 'Not provided';
        $customer_address = $_POST['address'] ?? 'Not provided';
        $customer_notes = $_POST['notes'] ?? '';
        $referral_source = $_POST['referralSource'] ?? 'Not specified';
        $referrer_name = $_POST['referrerName'] ?? '';
        $newsletter_opt = isset($_POST['newsletterOptIn']) && $_POST['newsletterOptIn'] === 'true' ? 'Yes' : 'No';
        
        // Get uploaded file details for email
        $file_list_html = '';
        if ($file_count > 0) {
            $stmt = $pdo->prepare("SELECT original_filename, file_path, file_size, mime_type FROM media WHERE quote_id = ?");
            $stmt->execute([$quote_id]);
            $uploaded_files = $stmt->fetchAll();
            
            $file_list_html = "<h2>Uploaded Files ({$file_count})</h2><ul style='list-style:none; padding:0;'>";
            foreach ($uploaded_files as $file) {
                $size_kb = round($file['file_size'] / 1024, 1);
                $file_url = "https://carpetree.com/{$file['file_path']}";
                $is_image = strpos($file['mime_type'], 'image/') === 0;
                $is_video = strpos($file['mime_type'], 'video/') === 0;
                $icon = $is_image ? '📷' : ($is_video ? '🎥' : '📎');
                
                $file_list_html .= "<li style='margin:8px 0; padding:10px; background:white; border-radius:4px;'>";
                $file_list_html .= "{$icon} <a href='{$file_url}' target='_blank'>" . htmlspecialchars($file['original_filename']) . "</a>";
                $file_list_html .= " <span style='color:#666;'>({$size_kb} KB - {$file['mime_type']})</span>";
                $file_list_html .= "</li>";
            }
            $file_list_html .= "</ul>";
        }
        
        // Selected services
        $services_html = '';
        if (!empty($selected_services)) {
            $services_html = "<h2>Selected Services</h2><ul style='list-style:none; padding:0;'>";
            foreach ($selected_services as $service) {
                $services_html .= "<li style='margin:5px 0; padding:8px; background:#e8f5e9; border-radius:4px;'>✓ " . htmlspecialchars($service) . "</li>";
            }
            $services_html .= "</ul>";
        }
        
        // GPS/Location data
        $location_html = '';
        if ($geo_lat && $geo_lng) {
            $maps_url = "https://www.google.com/maps?q={$geo_lat},{$geo_lng}";
            $location_html = "<div class='info-row'><span class='label'>GPS Location:</span> <a href='{$maps_url}' target='_blank'>View on Google Maps</a> ({$geo_lat}, {$geo_lng})</div>";
        }
        
        // Build immediate notification email with ALL data
        $immediate_subject = "NEW QUOTE #{$quote_id} - {$customer_name} - IMMEDIATE NOTIFICATION";
        
        $immediate_html = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #2D5A27, #4a7c59); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .info-row { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
            .label { font-weight: bold; color: #2D5A27; min-width: 120px; display: inline-block; }
            .urgent { background: #d4edda; border: 2px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 8px; }
            .footer { background: #2D5A27; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; }
            a { color: #2D5A27; }
            h2 { color: #2D5A27; border-bottom: 2px solid #2D5A27; padding-bottom: 5px; margin-top: 25px; }
        </style></head>
        <body>
            <div class='header'>
                <h1>New Quote Submitted - #{$quote_id}</h1>
                <p>Immediate notification - AI analysis will follow</p>
            </div>
            <div class='content'>
                <div class='urgent'>
                    <strong>NEW LEAD - IMMEDIATE ACTION</strong><br>
                    Quote #{$quote_id} submitted at " . date('Y-m-d H:i:s') . ". Customer data below. AI analysis processing separately.
                </div>
                
                <h2>Customer Information</h2>
                <div class='info-row'><span class='label'>Name:</span> " . htmlspecialchars($customer_name) . "</div>
                <div class='info-row'><span class='label'>Email:</span> <a href='mailto:" . htmlspecialchars($customer_email) . "'>" . htmlspecialchars($customer_email) . "</a></div>
                <div class='info-row'><span class='label'>Phone:</span> <a href='tel:" . htmlspecialchars($customer_phone) . "'>" . htmlspecialchars($customer_phone) . "</a></div>
                <div class='info-row'><span class='label'>Address:</span> " . htmlspecialchars($customer_address) . "</div>
                {$location_html}
                
                <h2>Quote Details</h2>
                <div class='info-row'><span class='label'>Quote ID:</span> #{$quote_id}</div>
                <div class='info-row'><span class='label'>Customer ID:</span> #{$customer_id}</div>
                <div class='info-row'><span class='label'>Submitted:</span> " . date('l, F j, Y \a\t g:i A') . "</div>
                <div class='info-row'><span class='label'>Files Uploaded:</span> {$file_count}</div>
                <div class='info-row'><span class='label'>Referral Source:</span> " . htmlspecialchars($referral_source) . ($referrer_name ? " - " . htmlspecialchars($referrer_name) : "") . "</div>
                <div class='info-row'><span class='label'>Newsletter:</span> {$newsletter_opt}</div>
                " . (!empty($customer_notes) ? "<div class='info-row'><span class='label'>Notes:</span><br>" . nl2br(htmlspecialchars($customer_notes)) . "</div>" : "") . "
                
                {$services_html}
                
                {$file_list_html}
                
                <h2>Quick Actions</h2>
                <p>
                    <a href='https://carpetree.com/admin-v2.html?quote_id={$quote_id}' style='display:inline-block; background:#2D5A27; color:white; padding:12px 24px; text-decoration:none; border-radius:25px; margin:5px; font-weight:bold;'>View Quote in Dashboard</a>
                    <a href='https://carpetree.com/customer-crm-dashboard.html?customer_id={$customer_id}' style='display:inline-block; background:#4a7c59; color:white; padding:12px 24px; text-decoration:none; border-radius:25px; margin:5px; font-weight:bold;'>View Customer Profile</a>
                    <a href='tel:" . htmlspecialchars($customer_phone) . "' style='display:inline-block; background:#1976d2; color:white; padding:12px 24px; text-decoration:none; border-radius:25px; margin:5px; font-weight:bold;'>Call Customer</a>
                </p>
            </div>
            <div class='footer'>
                <p>Carpe Tree'em - Professional Tree Care Services<br>778-655-3741 | sapport@carpetree.com</p>
            </div>
        </body>
        </html>";
        
        // Send immediate notification using direct method (no template dependency)
        $email_sent = sendEmailDirect($admin_email, $immediate_subject, $immediate_html, $quote_id);
        
        if ($email_sent) {
            error_log("IMMEDIATE admin notification sent for quote #{$quote_id} to {$admin_email}");
        } else {
            error_log("IMMEDIATE admin notification FAILED for quote #{$quote_id} - sendEmailDirect returned false");
        }
        
    } catch (Throwable $emailErr) {
        // Log but don't fail - the quote was already saved
        logError('Immediate admin email failed (quote still saved)', [
            'quote_id' => $quote_id,
            'error' => $emailErr->getMessage(),
            'trace' => $emailErr->getTraceAsString()
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
    // Reset captcha attempts on success
    $_SESSION['quote_attempts'] = 0;
    unset($_SESSION['quote_captcha_answer']);

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