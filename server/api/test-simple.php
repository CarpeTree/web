<?php
// Simplest possible endpoint - just return success
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Just return success with basic info
echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s'),
    'received_data' => [
        'post_count' => count($_POST),
        'files_count' => count($_FILES)
    ]
]);
?> 