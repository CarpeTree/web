<?php
// Production database configuration for Hostinger with debugging
echo "🔍 Starting database connection...\n";

// Check if config.php exists
$config_path = __DIR__ . '/config.php';
echo "Config path: " . $config_path . "\n";

if (!file_exists($config_path)) {
    echo "❌ ERROR: config.php not found at: " . $config_path . "\n";
    echo "Files in config directory:\n";
    $files = scandir(__DIR__);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "  - " . $file . "\n";
        }
    }
    die("Config file missing");
}

echo "✅ config.php found\n";

// Load config variables
require_once $config_path;
echo "✅ config.php loaded\n";

// Check if variables are set
echo "Checking config variables:\n";
echo "  DB_HOST: " . (isset($DB_HOST) ? $DB_HOST : 'NOT SET') . "\n";
echo "  DB_NAME: " . (isset($DB_NAME) ? $DB_NAME : 'NOT SET') . "\n";
echo "  DB_USER: " . (isset($DB_USER) ? $DB_USER : 'NOT SET') . "\n";
echo "  DB_PASS: " . (isset($DB_PASS) ? '[HIDDEN]' : 'NOT SET') . "\n";

// Use variables from config.php
$db_host = $DB_HOST ?? 'localhost';
$db_name = $DB_NAME ?? 'carpe_tree_quotes';
$db_user = $DB_USER ?? 'root';
$db_pass = $DB_PASS ?? '';
$db_charset = $DB_CHARSET ?? 'utf8mb4';

echo "Using connection settings:\n";
echo "  Host: " . $db_host . "\n";
echo "  Database: " . $db_name . "\n";
echo "  User: " . $db_user . "\n";

// Database connection with error handling
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    echo "Attempting connection...\n";
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✅ Database connection successful!\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    error_log("Database connection failed: " . $e->getMessage());
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed. Please try again later.']);
        exit;
    }
}
?> 