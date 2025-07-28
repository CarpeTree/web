<?php
// Production database configuration for Hostinger
// Check if config.php exists
$config_path = __DIR__ . '/config.php';

if (!file_exists($config_path)) {
    error_log("ERROR: config.php not found at: " . $config_path);
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Configuration file missing']);
        exit;
    }
    die("Config file missing");
}

// Load config variables
require_once $config_path;

// Use variables from config.php
$db_host = $DB_HOST ?? 'localhost';
$db_name = $DB_NAME ?? 'carpe_tree_quotes';
$db_user = $DB_USER ?? 'root';
$db_pass = $DB_PASS ?? '';
$db_charset = $DB_CHARSET ?? 'utf8mb4';

// Database connection with error handling
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed. Please try again later.']);
        exit;
    }
}
?> 