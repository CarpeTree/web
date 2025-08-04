<?php
// Configuration file example for Carpe Tree'em Quote System
// Copy this to config.php and fill in your actual values

// Database Configuration (from Hostinger)
$DB_HOST = 'localhost';
$DB_NAME = 'u230128646_carpetree';  // Your actual database name
$DB_USER = 'u230128646_carpetree';  // Your database username
$DB_PASS = 'your_database_password'; // Your database password
$DB_CHARSET = 'utf8mb4';

// Email Configuration (SMTP Settings)
$SMTP_HOST = 'smtp.hostinger.com';   // Hostinger SMTP
$SMTP_USER = 'quotes@carpetree.com'; // Your email address
$SMTP_PASS = 'your_email_password';  // Your email password
$SMTP_PORT = 587;                    // SMTP port (587 for TLS)
$SMTP_FROM = 'quotes@carpetree.com'; // From email address

// OpenAI Configuration (for AI analysis)
$OPENAI_API_KEY = 'sk-your-openai-api-key-here'; // Get from https://platform.openai.com/api-keys

// Site Configuration
$SITE_URL = 'https://carpetree.com';
$ADMIN_EMAIL = 'support@carpetree.com';
$ADMIN_URL = 'https://carpetree.com/admin';

// File Upload Configuration
$MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
$ALLOWED_FILE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mov', 'audio/mp3'];

// Business Information
$BUSINESS_NAME = 'Carpe Tree\'em';
$BUSINESS_PHONE = '778-655-3741';
$BUSINESS_EMAIL = 'support@carpetree.com';
$BUSINESS_ADDRESS = 'Greater Vancouver Area, BC';

// Quote Settings
$QUOTE_VALIDITY_DAYS = 30;
$DEFAULT_HOURLY_RATE = 150.00;
?> 