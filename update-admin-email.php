<?php
// Script to update admin email in config.php
// Run this once on the server to update the admin email

$config_file = __DIR__ . '/server/config/config.php';

if (!file_exists($config_file)) {
    die("Config file not found: $config_file\n");
}

$config_content = file_get_contents($config_file);

// Update the admin email
$updated_content = preg_replace(
    "/\\\$ADMIN_EMAIL = '[^']*';/",
    "\$ADMIN_EMAIL = 'sapport@carpetree.com';",
    $config_content
);

if ($updated_content !== $config_content) {
    file_put_contents($config_file, $updated_content);
    echo "✅ Admin email updated to: sapport@carpetree.com\n";
    echo "📧 Quote notifications will now go to this address.\n";
} else {
    echo "ℹ️ Admin email was already set to: sapport@carpetree.com\n";
}

// Show current config (without sensitive data)
echo "\n📋 Current admin configuration:\n";
include $config_file;
echo "Admin Email: $ADMIN_EMAIL\n";
echo "SMTP From: $SMTP_FROM\n";
echo "Site URL: $SITE_URL\n";
?> 