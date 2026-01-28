<?php
/**
 * Hostinger Media Receiver
 * 
 * Deploy this to your Hostinger shared hosting to receive media files
 * from the VPS after AI processing.
 * 
 * INSTALLATION:
 * 1. Upload this file to: public_html/media-api/receive.php on Hostinger
 * 2. Create directory: public_html/media/ (where files will be stored)
 * 3. Set MEDIA_AUTH_TOKEN environment variable or edit $authToken below
 * 4. Ensure the media directory is writable by PHP
 */

// CONFIGURATION - Change this or use environment variable
$authToken = getenv('MEDIA_AUTH_TOKEN') ?: 'YOUR_SECURE_TOKEN_HERE';

// Storage configuration
$baseStorageDir = dirname(__DIR__) . '/media';  // ../media relative to this script
$maxFileSize = 500 * 1024 * 1024;  // 500MB max
$allowedMimeTypes = [
    // Images
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif',
    // Videos
    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska',
    'video/3gpp', 'video/x-m4v',
    // Audio
    'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac', 'audio/flac',
    'audio/webm', 'audio/x-m4a',
    // Also allow octet-stream as fallback
    'application/octet-stream'
];

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify authentication
$providedToken = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $providedToken = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_POST['auth_token'])) {
    $providedToken = $_POST['auth_token'];
}

if ($authToken !== 'YOUR_SECURE_TOKEN_HERE' && $providedToken !== $authToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get quote_id
$quoteId = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;
if ($quoteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid quote_id']);
    exit;
}

// Check for file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file uploaded';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "File upload error: $error"]);
    exit;
}

$file = $_FILES['file'];

// Validate file size
if ($file['size'] > $maxFileSize) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}

// Validate MIME type
$mimeType = mime_content_type($file['tmp_name']) ?: $file['type'];
if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => "Unsupported file type: $mimeType"]);
    exit;
}

// Determine destination filename
$filename = isset($_POST['filename']) ? basename($_POST['filename']) : $file['name'];
// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
$filename = preg_replace('/\.+/', '.', $filename);  // Remove multiple dots

// Create quote directory
$quoteDir = $baseStorageDir . '/quote_' . $quoteId;
if (!is_dir($quoteDir)) {
    if (!mkdir($quoteDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create storage directory']);
        exit;
    }
}

// Handle filename conflicts
$destPath = $quoteDir . '/' . $filename;
if (file_exists($destPath)) {
    $pathInfo = pathinfo($filename);
    $base = $pathInfo['filename'];
    $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
    $i = 1;
    while (file_exists($quoteDir . '/' . $base . '_' . $i . $ext)) {
        $i++;
    }
    $filename = $base . '_' . $i . $ext;
    $destPath = $quoteDir . '/' . $filename;
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Set proper permissions
chmod($destPath, 0644);

// Generate public URL
// Adjust this based on your Hostinger domain configuration
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$publicUrl = $scheme . '://' . $host . '/media/quote_' . $quoteId . '/' . rawurlencode($filename);

// Log the upload
$logFile = $baseStorageDir . '/upload.log';
$logEntry = date('Y-m-d H:i:s') . " | Quote: $quoteId | File: $filename | Size: {$file['size']} | From: {$_SERVER['REMOTE_ADDR']}\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND);

// Return success response
echo json_encode([
    'success' => true,
    'url' => $publicUrl,
    'filename' => $filename,
    'size' => filesize($destPath),
    'mime_type' => $mimeType,
    'quote_id' => $quoteId
]);
