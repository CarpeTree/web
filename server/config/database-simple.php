<?php
// Production database configuration for Hostinger
// Load config variables
require_once __DIR__ . '/config.php';

// Use variables from config.php
$db_host = $DB_HOST;
$db_name = $DB_NAME;
$db_user = $DB_USER;
$db_pass = $DB_PASS;
$db_charset = $DB_CHARSET;

// Database connection with error handling
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    // Success indicator for debugging
    if (php_sapi_name() === 'cli') {
        echo "✅ Database connection successful!\n";
    }
} catch (PDOException $e) {
    // Log the error but don't expose details to users
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error
    if (php_sapi_name() === 'cli') {
        echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed. Please try again later.']);
        exit;
    }
}
?> 