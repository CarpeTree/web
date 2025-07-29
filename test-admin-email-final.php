<?php
// Final Admin Email Test
require_once 'server/config/config.php';
require_once 'server/config/database-simple.php';
require_once 'server/utils/mailer.php';

echo "🎯 Final Admin Email Test\n";
echo "Admin Email: $ADMIN_EMAIL\n\n";

// Test using the new direct email function
echo "📧 Sending direct test email...\n";

$html_content = "
<html>
<body style='font-family: Arial, sans-serif; margin: 20px;'>
    <h2 style='color: #2c5530;'>✅ CarpeTree Admin Email Test</h2>
    <p>This is a <strong>successful test</strong> of the admin notification system!</p>
    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>
        <p><strong>System Status:</strong> ✅ Working</p>
        <p><strong>Admin Email:</strong> $ADMIN_EMAIL</p>
        <p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>
    <p>If you receive this email, your admin notifications are working correctly!</p>
    <hr>
    <small>CarpeTree Admin Notification System</small>
</body>
</html>
";

$result = sendEmailDirect(
    $ADMIN_EMAIL,
    '🎯 CarpeTree Admin Email Test - SUCCESS',
    $html_content
);

if ($result) {
    echo "✅ Direct test email sent successfully!\n";
} else {
    echo "❌ Direct test email failed!\n";
}

// Also test admin notification function
echo "\n📊 Testing admin notification function...\n";

try {
    $stmt = $pdo->prepare("SELECT q.id FROM quotes q ORDER BY q.id DESC LIMIT 1");
    $stmt->execute();
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quote) {
        echo "Testing with Quote ID: {$quote['id']}\n";
        
        require_once 'server/api/admin-notification.php';
        $admin_result = sendAdminNotification($quote['id']);
        
        if ($admin_result) {
            echo "✅ Admin notification sent successfully!\n";
        } else {
            echo "❌ Admin notification failed!\n";
        }
    } else {
        echo "⚠️ No quotes found to test\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Admin email testing complete!\n";
echo "📫 Check your inbox at: $ADMIN_EMAIL\n";
?> 