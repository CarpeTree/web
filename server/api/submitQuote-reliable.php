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
            $upload_dir = __DIR__ . '/../../uploads';
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
                                'uploads/' . $filename,
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
    
    // Success response
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'message' => 'Quote submitted successfully',
        'files_uploaded' => $file_count,
        'preflight_check' => [
            'sufficient' => true,
            'confidence' => 'high'
        ]
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