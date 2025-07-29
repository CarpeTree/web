<?php
// Simple Admin Email Test
require_once 'server/config/config.php';
require_once 'server/config/database-simple.php';
require_once 'server/utils/mailer.php';

echo "🔧 Testing Admin Email System\n";
echo "Admin Email: $ADMIN_EMAIL\n\n";

// Test 1: Simple email test
echo "📧 Test 1: Sending simple test email...\n";

$test_result = sendEmail(
    $ADMIN_EMAIL,
    'CarpeTree Admin Email Test',
    'This is a simple test. If you receive this, the email system works!',
    '<h2>✅ Email Test Successful</h2><p>The CarpeTree admin notification system is working!</p>',
    null // No quote ID
);

if ($test_result) {
    echo "✅ Test email sent successfully!\n";
} else {
    echo "❌ Test email failed!\n";
}

// Test 2: Check if we have any quotes to test with
echo "\n📊 Test 2: Checking for recent quotes...\n";

try {
    $stmt = $pdo->prepare("SELECT q.id, c.name, c.email FROM quotes q JOIN customers c ON q.customer_id = c.id ORDER BY q.id DESC LIMIT 1");
    $stmt->execute();
    $recent_quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($recent_quote) {
        echo "Found Quote #{$recent_quote['id']} from {$recent_quote['name']} ({$recent_quote['email']})\n";
        
        // Test 3: Send admin notification for this quote
        echo "\n🔔 Test 3: Sending admin notification...\n";
        
        require_once 'server/api/admin-notification.php';
        $admin_result = sendAdminNotification($recent_quote['id']);
        
        if ($admin_result) {
            echo "✅ Admin notification sent successfully!\n";
        } else {
            echo "❌ Admin notification failed!\n";
        }
        
    } else {
        echo "⚠️ No quotes found to test admin notification\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Check your email ($ADMIN_EMAIL) for test messages!\n";
?> 