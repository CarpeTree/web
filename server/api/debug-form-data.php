<?php
// Debug endpoint to see what data the form is actually sending
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$debug_info = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'post_data' => $_POST,
    'files_data' => $_FILES,
    'raw_input' => file_get_contents('php://input'),
    'request_headers' => getallheaders()
];

// Log to file for debugging
file_put_contents(__DIR__ . '/../../debug-form-submission.log', 
    date('Y-m-d H:i:s') . "\n" . print_r($debug_info, true) . "\n\n", 
    FILE_APPEND
);

echo json_encode([
    'success' => true,
    'debug' => $debug_info
]);
?> 