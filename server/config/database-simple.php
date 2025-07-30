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

// Load environment variables from .env if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
        try {
            $dotenv->load();
        } catch (Exception $e) {
            // .env not found or failed to parse - not critical
        }
    }
}

// Use variables from config.php OR environment variables
$db_host = $_ENV['DB_HOST'] ?? $_ENV['MYSQL_HOST'] ?? ($DB_HOST ?? 'localhost');
$db_name = $_ENV['DB_NAME'] ?? $_ENV['MYSQL_DATABASE'] ?? ($DB_NAME ?? 'carpe_tree_quotes');
$db_user = $_ENV['DB_USER'] ?? $_ENV['MYSQL_USER'] ?? ($DB_USER ?? 'root');
$db_pass = $_ENV['DB_PASS'] ?? $_ENV['MYSQL_PASSWORD'] ?? ($DB_PASS ?? '');
$db_charset = $_ENV['DB_CHARSET'] ?? ($DB_CHARSET ?? 'utf8mb4');

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