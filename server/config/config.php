<?php
// Central configuration bootstrap (no secrets committed here)
// - Loads .env if available
// - Exposes getDatabaseConnection() and key env-based variables

declare(strict_types=1);

// Autoload (Dotenv resides under server/vendor)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('Dotenv\Dotenv')) {
        try {
            // Project root: two levels above this file
            Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->load();
        } catch (Throwable $e) {
            // ignore
        }
    }
}

/**
 * Get env value with default.
 */
function cfg_env(string $key, $default = null) {
    if (array_key_exists($key, $_ENV)) return $_ENV[$key];
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

/**
 * Shared PDO connection
 */
function getDatabaseConnection(): ?PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $db_host = cfg_env('DB_HOST', 'localhost');
    $db_name = cfg_env('DB_NAME', 'carpetree');
    $db_user = cfg_env('DB_USER', 'carpetree');
    $db_pass = cfg_env('DB_PASS', '');
    $db_charset = cfg_env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } catch (Throwable $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        return null;
    }
    return $pdo;
}

// Common service credentials (read from env)
$OPENAI_API_KEY = cfg_env('OPENAI_API_KEY', '');
$GOOGLE_GEMINI_API_KEY = cfg_env('GOOGLE_GEMINI_API_KEY', '');
// Google Maps Distance Matrix API key (for travel distances)
$GOOGLE_MAPS_API_KEY = cfg_env('GOOGLE_MAPS_API_KEY', '');
// Optional: Home/base coordinates to avoid geocoding when GPS is not available
$HOME_LAT = cfg_env('HOME_LAT', '');
$HOME_LNG = cfg_env('HOME_LNG', '');
$SMTP_HOST = cfg_env('SMTP_HOST', '');
$SMTP_PORT = (int) cfg_env('SMTP_PORT', '587');
$SMTP_USER = cfg_env('SMTP_USER', '');
$SMTP_PASS = cfg_env('SMTP_PASS', '');
$SMTP_FROM = cfg_env('SMTP_FROM', 'sapport@carpetree.com');
$ADMIN_EMAIL = cfg_env('ADMIN_EMAIL', '');
$SITE_URL = cfg_env('SITE_URL', 'https://carpetree.com');
// Simple admin token for lightweight endpoints (optional)
$ADMIN_TOKEN = cfg_env('ADMIN_TOKEN', '');

?>