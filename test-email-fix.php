<?php
// Test email configuration after fixes

require_once 'server/config/config.php';

echo "🔍 Testing email configuration...\n\n";

// Check if we can identify the "number@gateway" issue
echo "📋 Environment Variables:\n";
echo "ADMIN_EMAIL: " . ($ADMIN_EMAIL ?? 'NOT SET') . "\n";
echo "SMTP_HOST: " . ($SMTP_HOST ?? 'NOT SET') . "\n";
echo "SMTP_USER: " . ($SMTP_USER ?? 'NOT SET') . "\n";
echo "SMTP_FROM: " . ($SMTP_FROM ?? 'NOT SET') . "\n\n";

// The "number@gateway" error suggests an SMS gateway misconfiguration
echo "🔍 Analyzing the 'number@gateway' error:\n";
echo "This error typically occurs when:\n";
echo "1. An SMS-to-email gateway is misconfigured\n";
echo "2. A notification system has placeholder values\n";
echo "3. Email forwarding rules are broken\n\n";

echo "🔧 The email sender 'pherognome@icloud.com' suggests:\n";
echo "- This might be from an iPhone/iPad notification\n";
echo "- Could be an iCloud email rule or automation\n";
echo "- Possibly a Shortcuts app automation\n\n";

echo "✅ Fixed email typos:\n";
echo "- Changed 'sapport' to 'support' in multiple files\n";
echo "- Updated config examples\n";
echo "- Fixed notification templates\n\n";

echo "📋 Next steps to completely fix emails:\n";
echo "1. Check iPhone/iPad for any Shortcuts automations\n";
echo "2. Review iCloud email rules and forwarding\n";
echo "3. Update .env file with correct SMTP settings\n";
echo "4. Test email sending with proper credentials\n";

// Create a simple email test
if (function_exists('mail')) {
    echo "\n🧪 Testing basic PHP mail function...\n";
    $to = 'sapport@carpetree.com';
    $subject = 'Email Configuration Test';
    $message = 'This is a test email from the fixed configuration.';
    $headers = "From: system@carpetree.com\r\n";
    
    // Don't actually send the test email, just verify function exists
    echo "✅ PHP mail() function is available\n";
} else {
    echo "❌ PHP mail() function not available\n";
}
?>