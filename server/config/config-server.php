<?php
/**
 * Configuration file for Carpe Tree website
 */

// Load environment variables from .env file
$env_path = realpath(__DIR__ . '/../../') . '/.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key, " \t\n\r\0\x0B");
            $value = trim($value, " \t\n\r\0\x0B");
            putenv("$key=$value");
        }
    }
}

// Database configuration
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: '';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASS = getenv('DB_PASS') ?: '';

// API Keys
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: '';
$GOOGLE_GEMINI_API_KEY = getenv('GOOGLE_GEMINI_API_KEY') ?: '';
$GOOGLE_MAPS_API_KEY = getenv('GOOGLE_MAPS_API_KEY') ?: '';

// SMTP configuration
$SMTP_HOST = getenv('SMTP_HOST') ?: '';
$SMTP_PORT = getenv('SMTP_PORT') ?: 587;
$SMTP_USER = getenv('SMTP_USER') ?: '';
$SMTP_PASS = getenv('SMTP_PASS') ?: '';
$SMTP_FROM = getenv('SMTP_FROM') ?: '';

// Site configuration
$SITE_URL = getenv('SITE_URL') ?: 'https://carpetree.com';
$ADMIN_EMAIL = getenv('ADMIN_EMAIL') ?: '';

// Function to get database connection with retry logic
function getDatabaseConnection() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    
    $maxRetries = 3;
    $retryDelay = 1; // seconds
    
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode=''",
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection attempt " . ($i + 1) . " failed: " . $e->getMessage());
            if ($i < $maxRetries - 1) {
                sleep($retryDelay);
            }
        }
    }
    return null;
}

// Initial PDO connection
$pdo = getDatabaseConnection();
if (!$pdo) {
    error_log("Failed to establish database connection after all retries");
}
?>