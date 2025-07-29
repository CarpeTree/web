<?php
// START NEW - Quote submission endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database-simple.php';
require_once '../config/config.php';
require_once '../utils/fileHandler.php';
require_once '../utils/mailer.php';

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

    // Insert or update customer
    $stmt = $pdo->prepare("
        INSERT INTO customers (email, name, phone, address, referral_source, referrer_name, newsletter_opt_in)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = COALESCE(VALUES(name), name),
        phone = COALESCE(VALUES(phone), phone),
        address = COALESCE(VALUES(address), address),
        referral_source = COALESCE(VALUES(referral_source), referral_source),
        referrer_name = COALESCE(VALUES(referrer_name), referrer_name),
        newsletter_opt_in = VALUES(newsletter_opt_in)
    ");

    $stmt->execute([
        $_POST['email'],
        $_POST['name'] ?? null,
        $_POST['phone'] ?? null,
        $_POST['address'] ?? null,
        $_POST['referralSource'] ?? null,
        $_POST['referrerName'] ?? null,
        isset($_POST['newsletterOptIn']) && $_POST['newsletterOptIn'] === 'true' ? 1 : 0
    ]);

    // Get customer ID
    $customer_id = $pdo->lastInsertId();
    if (!$customer_id) {
        // Customer already exists, get their ID
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $customer_id = $stmt->fetchColumn();
    }

    // Insert quote
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            customer_id, quote_status, selected_services, 
            gps_lat, gps_lng, exif_lat, exif_lng, notes
        ) VALUES (?, 'submitted', ?, ?, ?, ?, ?, ?)
    ");

    $selected_services = isset($_POST['selectedServices']) ? $_POST['selectedServices'] : '[]';
    
    $stmt->execute([
        $customer_id,
        $selected_services,
        $_POST['gpsLat'] ?? null,
        $_POST['gpsLng'] ?? null,
        $_POST['exifLat'] ?? null,
        $_POST['exifLng'] ?? null,
        $_POST['notes'] ?? null
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
    }

    // Update quote status based on whether files were uploaded
    if (!empty($uploaded_files)) {
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
        $stmt->execute([$quote_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted_no_files' WHERE id = ?");
        $stmt->execute([$quote_id]);
    }

    // Commit transaction
    $pdo->commit();

    // Send immediate confirmation email
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

    // Trigger AI processing asynchronously if files were uploaded
    if (!empty($uploaded_files)) {
        $ai_script = __DIR__ . '/aiQuote.php';
        $command = "cd " . dirname(__DIR__) . " && php api/aiQuote.php $quote_id > /dev/null 2>&1 &";
        exec($command);
        $message = 'Quote submitted successfully. AI analysis will begin shortly.';
    } else {
        $message = 'Quote submitted successfully. We will contact you to schedule an in-person assessment.';
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'customer_id' => $customer_id,
        'uploaded_files' => count($uploaded_files),
        'email_sent' => $email_sent,
        'message' => $message
    ]);

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

function processUploadedFile($file, $quote_id, $upload_dir, $pdo) {
    // Validate file type
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'audio/mpeg', 'audio/mp4', 'audio/wav'
    ];

    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        return false;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $file_path = $upload_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        error_log("Failed to move uploaded file");
        return false;
    }

    // Determine file type category
    $file_type = 'image';
    if (strpos($file['type'], 'video/') === 0) {
        $file_type = 'video';
    } elseif (strpos($file['type'], 'audio/') === 0) {
        $file_type = 'audio';
    }

    // Extract EXIF data for images
    $exif_data = null;
    if ($file_type === 'image' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file_path);
        if ($exif) {
            $exif_data = json_encode($exif);
        }
    }

    // Insert media record
    $stmt = $pdo->prepare("
        INSERT INTO media (
            quote_id, filename, original_filename, file_path, 
            file_type, file_size, mime_type, exif_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $quote_id,
        $filename,
        $file['name'],
        $file_path,
        $file_type,
        $file['size'],
        $file['type'],
        $exif_data
    ]);

    return [
        'filename' => $filename,
        'original_name' => $file['name'],
        'type' => $file_type,
        'size' => $file['size']
    ];
}
// END NEW
?> 