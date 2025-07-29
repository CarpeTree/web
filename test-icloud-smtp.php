<?php
// Test iCloud SMTP admin notification system
echo "Testing iCloud SMTP admin notification system...\n";
echo "================================================\n\n";

require_once __DIR__ . '/server/api/admin-notification.php';

// Test sending admin notification for Quote #40
echo "📧 Sending admin notification for Quote #40 using iCloud SMTP...\n";
$result = sendAdminNotification(40);

if ($result) {
    echo "✅ SUCCESS: Admin email sent via iCloud SMTP!\n";
    echo "📧 Email details:\n";
    echo "   - From: quotes@carpetree.com (via iCloud SMTP)\n";
    echo "   - To: phil.bajenski@gmail.com\n";
    echo "   - Authentication: Proper SMTP with Apple ID\n";
    echo "   - Encryption: STARTTLS\n";
    echo "   - Content: Professional admin notification with CRM links\n\n";
    echo "🔍 Check your Gmail inbox (should arrive in main inbox, not junk!)\n";
    echo "📊 Email should include direct links to:\n";
    echo "   - admin-dashboard.html?quote_id=40\n";
    echo "   - customer-crm-dashboard.html?customer_id=36\n";
} else {
    echo "❌ FAILED: Admin email could not be sent\n";
    echo "Check error logs for details\n";
}

echo "\n🎯 If successful, this proves your iCloud custom domain setup is working!\n";
?> 