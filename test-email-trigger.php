<?php
// Test email system with sapport@carpetree.com
require_once 'server/config/config.php';

echo "🧪 Testing email system with sapport@carpetree.com...\n";

// Check current email configuration
echo "📧 Current email settings:\n";
echo "ADMIN_EMAIL: " . ($ADMIN_EMAIL ?: 'not set') . "\n";
echo "SMTP_HOST: " . ($SMTP_HOST ?: 'not set') . "\n";
echo "SMTP_USER: " . ($SMTP_USER ?: 'not set') . "\n";
echo "SMTP_FROM: " . ($SMTP_FROM ?: 'not set') . "\n\n";

// Test basic email sending
function testBasicEmail() {
    $to = 'sapport@carpetree.com';
    $subject = '🧪 Email System Test - ' . date('Y-m-d H:i:s');
    $message = "
    <html>
    <body>
        <h2>📧 Email Test Successful!</h2>
        <p>This is a test email to verify the sapport@carpetree.com system is working.</p>
        <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>From:</strong> Carpe Tree Website</p>
        <hr>
        <p>If you receive this email, the email system is functioning properly!</p>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Carpe Tree System <noreply@carpetree.com>\r\n";
    $headers .= "Reply-To: sapport@carpetree.com\r\n";
    
    echo "📤 Sending test email to sapport@carpetree.com...\n";
    
    $result = mail($to, $subject, $message, $headers);
    
    if ($result) {
        echo "✅ Email sent successfully!\n";
        echo "📬 Check your sapport@carpetree.com inbox\n";
    } else {
        echo "❌ Email sending failed\n";
        echo "Check server mail configuration\n";
    }
    
    return $result;
}

// Test admin notification system
function testAdminNotification() {
    echo "\n🔔 Testing admin notification system...\n";
    
    // Try to use the existing admin notification
    if (file_exists('server/api/admin-notification-simple.php')) {
        echo "📤 Triggering admin notification...\n";
        
        // Simulate a test quote
        $_POST = [
            'quote_id' => 'TEST_' . time(),
            'test_mode' => true
        ];
        
        // Include and test the notification system
        ob_start();
        include 'server/api/admin-notification-simple.php';
        $output = ob_get_clean();
        
        echo "📋 Admin notification result:\n";
        echo substr($output, 0, 200) . "...\n";
    } else {
        echo "❌ Admin notification file not found\n";
    }
}

// Run tests
echo "🚀 Starting email tests...\n\n";

// Test 1: Basic email
testBasicEmail();

// Test 2: Admin notification (if available)
testAdminNotification();

echo "\n✅ Email test complete!\n";
echo "📱 Check your email (including spam folder) for test messages\n";
echo "🔍 Monitor server logs for any email errors\n";
?>