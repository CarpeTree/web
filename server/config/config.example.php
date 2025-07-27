<?php
// Example configuration file
// Copy this to config.php and add your actual API keys

// OpenAI Configuration
$OPENAI_API_KEY = 'sk-your-openai-api-key-here';

// Email Configuration
$SMTP_HOST = 'smtp.hostinger.com';
$SMTP_PORT = 587;
$SMTP_USER = 'your-email@yourdomain.com';
$SMTP_PASS = 'your-email-password';
$SMTP_FROM = 'noreply@carpetree.com';

// Admin Configuration
$ADMIN_EMAIL = 'admin@carpetree.com';
$SITE_URL = 'https://yourdomain.com'; // Update for production

// Security Check
if (empty($OPENAI_API_KEY) || $OPENAI_API_KEY === 'sk-your-openai-api-key-here') {
    error_log("⚠️  WARNING: OpenAI API key not configured properly");
}
?> 