<?php
// Most basic test - no includes, no dependencies
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'status' => 'basic_php_working',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
]);
?> 