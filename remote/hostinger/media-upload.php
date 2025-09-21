<?php
// Secure media upload receiver for Hostinger (remote storage)
// Auth: Bearer token in Authorization header; set MEDIA_UPLOAD_TOKEN in environment

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function fail($status, $msg) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'method_not_allowed');

    // --- Auth ---
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        if (isset($h['Authorization'])) $auth = $h['Authorization'];
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) fail(401, 'missing_bearer');
    $provided = trim($m[1]);
    $expected = getenv('MEDIA_UPLOAD_TOKEN') ?: '';
    if ($expected === '' || !hash_equals($expected, $provided)) fail(401, 'unauthorized');

    // --- Inputs ---
    $quote_id = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;
    if ($quote_id <= 0) fail(400, 'invalid_quote_id');
    if (!isset($_FILES['file'])) fail(400, 'missing_file');

    $filename = $_POST['filename'] ?? ($_FILES['file']['name'] ?? 'upload.bin');
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

    // --- Validate file ---
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail(400, 'upload_error_' . $file['error']);
    $allowed = [
        'image/jpeg','image/png','image/webp','image/gif','image/heic',
        'video/mp4','video/quicktime','video/x-msvideo','video/mov','video/avi',
        'audio/mpeg','audio/mp4','audio/wav','audio/x-wav'
    ];
    $mime = $file['type'] ?: 'application/octet-stream';
    if (!in_array($mime, $allowed, true)) fail(415, 'unsupported_type');
    $max = 1024*1024*1024; // 1GB
    if ($file['size'] > $max) fail(413, 'file_too_large');

    // --- Save under public uploads ---
    $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
    $base = $doc . '/uploads/quote_' . $quote_id;
    if (!is_dir($base) && !@mkdir($base, 0755, true)) fail(500, 'mkdir_failed');

    $dest = $base . '/' . $filename;
    // ensure unique name if collision
    if (file_exists($dest)) {
        $pi = pathinfo($filename);
        $stem = $pi['filename'] ?? 'file';
        $ext = isset($pi['extension']) ? '.' . $pi['extension'] : '';
        $dest = $base . '/' . $stem . '_' . time() . $ext;
        $filename = basename($dest);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) fail(500, 'move_failed');

    // --- Build public URL ---
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url = $scheme . '://' . $host . '/uploads/quote_' . $quote_id . '/' . rawurlencode($filename);

    echo json_encode(['ok' => true, 'url' => $url]);
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}
?>


