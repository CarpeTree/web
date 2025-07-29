<?php
// Test admin notification system
echo "Testing Admin Notification System...\n";
echo "====================================\n\n";

require_once __DIR__ . '/server/api/admin-notification.php';

// Test with Quote #49 (latest submission)
$quote_id = 49;

echo "📧 Testing admin notification for Quote #$quote_id...\n";

try {
    $result = sendAdminNotification($quote_id);
    
    if ($result) {
        echo "✅ Admin notification sent successfully!\n";
        echo "   Email should be delivered to sapport@carpetree.com\n";
    } else {
        echo "❌ Admin notification failed to send\n";
        echo "   Check SMTP configuration and error logs\n";
    }
} catch (Exception $e) {
    echo "💥 Exception occurred: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🔍 Checking email configuration...\n";
require_once __DIR__ . '/server/config/config.php';

echo "Admin Email: " . ($ADMIN_EMAIL ?? 'NOT SET') . "\n";
echo "SMTP Host: " . ($SMTP_HOST ?? 'NOT SET') . "\n";
echo "SMTP User: " . ($SMTP_USER ?? 'NOT SET') . "\n";
echo "SMTP From: " . ($SMTP_FROM ?? 'NOT SET') . "\n";
?> 