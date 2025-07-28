<?php
// PRODUCTION CONFIG FOR HOSTINGER
// Upload this as server/config/config.php on your live server

// OpenAI Configuration
$OPENAI_API_KEY = 'YOUR_OPENAI_API_KEY_HERE';

// Email Configuration (iCloud with custom domain)
$SMTP_HOST = 'smtp.mail.me.com';
$SMTP_PORT = 587;
$SMTP_USER = 'pherognome@icloud.com';
$SMTP_PASS = 'YOUR_ICLOUD_APP_PASSWORD_HERE';
$SMTP_FROM = 'sapport@carpetree.com';

// Admin Configuration
$ADMIN_EMAIL = 'phil.bajenski@gmail.com';
$SITE_URL = 'https://carpetree.com';

// Database Configuration - Hostinger Details
$DB_HOST = 'localhost';
$DB_NAME = 'u230128646_carpetree';
$DB_USER = 'u230128646_pherognome';
$DB_PASS = 'YOUR_DATABASE_PASSWORD_HERE';
$DB_CHARSET = 'utf8mb4';

// Security Check
if (empty($OPENAI_API_KEY)) {
    error_log("⚠️  WARNING: OpenAI API key not configured");
}

if (empty($SMTP_USER)) {
    error_log("ℹ️  INFO: Email not configured");
}
?> 