<?php
// Simple health check to diagnose server issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in output

$health_check = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'status' => 'ok'
];

try {
    // Test database connection
    require_once __DIR__ . '/../config/database-simple.php';
    $health_check['database'] = 'connected';
    
    // Test basic query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
    $stmt->execute();
    $result = $stmt->fetch();
    $health_check['customers_count'] = $result['count'];
    
} catch (Exception $e) {
    $health_check['database_error'] = $e->getMessage();
    $health_check['status'] = 'database_error';
}

try {
    // Test config loading
    require_once __DIR__ . '/../config/config.php';
    $health_check['config'] = 'loaded';
    $health_check['smtp_configured'] = !empty($SMTP_USER ?? '');
    
} catch (Exception $e) {
    $health_check['config_error'] = $e->getMessage();
    $health_check['status'] = 'config_error';
}

try {
    // Test mailer loading
    require_once __DIR__ . '/../utils/mailer.php';
    $health_check['mailer'] = 'loaded';
    
} catch (Exception $e) {
    $health_check['mailer_error'] = $e->getMessage();
    $health_check['status'] = 'mailer_error';
}

echo json_encode($health_check, JSON_PRETTY_PRINT);
?> 