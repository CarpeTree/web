<?php
/**
 * Secure Configuration Loader
 * Loads API keys from environment variables or .env file
 * NEVER hardcode API keys in this file
 */

// Load from .env files if present (project root then config directory)
$env_candidates = [dirname(__DIR__, 2) . '/.env', __DIR__ . '/.env'];
foreach ($env_candidates as $env_file) {
    if (!file_exists($env_file)) {
        continue;
    }
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue; // Skip invalid lines

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        $value = trim($value, '"\'');

        // Set as environment variable if not already set
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Define constants from environment variables
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');
}

if (!defined('GOOGLE_GEMINI_API_KEY')) {
    define('GOOGLE_GEMINI_API_KEY', getenv('GOOGLE_GEMINI_API_KEY') ?: '');
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}

// Email configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.mail.me.com');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
}

if (!defined('SMTP_USER')) {
    define('SMTP_USER', getenv('SMTP_USER') ?: '');
}

if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
}

if (!defined('SMTP_FROM')) {
    define('SMTP_FROM', getenv('SMTP_FROM') ?: '');
}

if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');
}

if (!defined('SITE_URL')) {
    define('SITE_URL', getenv('SITE_URL') ?: 'https://carpetree.com');
}

// Make available as globals for legacy code
$GOOGLE_MAPS_API_KEY = GOOGLE_MAPS_API_KEY;
$GOOGLE_GEMINI_API_KEY = GOOGLE_GEMINI_API_KEY;
$OPENAI_API_KEY = OPENAI_API_KEY;

// Security check - warn if API keys are missing
if (empty(GOOGLE_MAPS_API_KEY) && php_sapi_name() !== 'cli') {
    error_log('WARNING: GOOGLE_MAPS_API_KEY not configured');
}

if (empty(GOOGLE_GEMINI_API_KEY) && php_sapi_name() !== 'cli') {
    error_log('WARNING: GOOGLE_GEMINI_API_KEY not configured');
}
