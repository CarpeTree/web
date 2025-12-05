<?php
// Simple, robust database bootstrap
// - config.php is optional; we also support .env via vlucas/phpdotenv if vendor is present

$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    error_log('database-simple.php: config.php not found; falling back to environment only');
}

// Load environment variables from project .env if available
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('Dotenv\Dotenv')) {
        try {
            // Project root is two levels up from this file: .../server/config -> project root
            Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->load();
        } catch (Throwable $e) {
            // ignore
        }
    }
}

// Resolve DB settings from env first, then config.php variables, then sane defaults
$db_host = $_ENV['DB_HOST'] ?? $_ENV['MYSQL_HOST'] ?? ($DB_HOST ?? 'localhost');
$db_name = $_ENV['DB_NAME'] ?? $_ENV['MYSQL_DATABASE'] ?? ($DB_NAME ?? 'carpetree');
$db_user = $_ENV['DB_USER'] ?? $_ENV['MYSQL_USER'] ?? ($DB_USER ?? 'root');
$db_pass = $_ENV['DB_PASS'] ?? $_ENV['MYSQL_PASSWORD'] ?? ($DB_PASS ?? '');
$db_charset = $_ENV['DB_CHARSET'] ?? ($DB_CHARSET ?? 'utf8mb4');

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}