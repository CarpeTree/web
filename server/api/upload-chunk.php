<?php
// Chunked file upload handler with progress tracking
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database-simple.php';

try {
    if (!isset($_FILES['file']) || !isset($_POST['file_id'])) {
        throw new Exception('File and file_id required');
    }
    
    $file = $_FILES['file'];
    $file_id = $_POST['file_id'];
    $upload_type = $_POST['upload_type'] ?? 'complete';
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mov', 'video/avi', 'video/quicktime'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('File type not allowed: ' . $file['type']);
    }
    
    // Check file size (100MB limit)
    $max_size = 100 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 100MB.');
    }
    
    // Create temporary storage directory
    $temp_dir = __DIR__ . '/../uploads/temp';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = $file_id . '_' . time() . '.' . $file_extension;
    $temp_path = $temp_dir . '/' . $unique_filename;
    
    // Move uploaded file to temporary location
    if (!move_uploaded_file($file['tmp_name'], $temp_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Store file info in session/database for later use
    session_start();
    if (!isset($_SESSION['uploaded_files'])) {
        $_SESSION['uploaded_files'] = [];
    }
    
    $_SESSION['uploaded_files'][$file_id] = [
        'original_name' => $file['name'],
        'temp_path' => $temp_path,
        'file_size' => $file['size'],
        'mime_type' => $file['type'],
        'uploaded_at' => time()
    ];
    
    // Clean up old temp files (older than 1 hour)
    cleanupOldTempFiles($temp_dir);
    
    echo json_encode([
        'success' => true,
        'file_id' => $file_id,
        'filename' => $unique_filename,
        'size' => $file['size'],
        'type' => $file['type'],
        'message' => 'File uploaded successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function cleanupOldTempFiles($temp_dir) {
    $files = glob($temp_dir . '/*');
    $one_hour_ago = time() - 3600;
    
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $one_hour_ago) {
            unlink($file);
        }
    }
}
?> 