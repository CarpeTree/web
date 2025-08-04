<?php
// Test the email system to see why admin notifications aren't being sent

echo "🧪 Testing Quote Submission Email System\n";
echo "=======================================\n\n";

echo "📧 Checking admin email configuration...\n";

// Load config
require_once 'server/config/config.php';

echo "✅ Config loaded\n";
echo "📬 Admin email: " . ($ADMIN_EMAIL ?? 'NOT SET') . "\n";
echo "📬 Business email: " . ($BUSINESS_EMAIL ?? 'NOT SET') . "\n\n";

// Test admin notification directly
echo "📤 Testing admin notification...\n";

try {
    require_once 'server/api/admin-notification-simple.php';
    
    // Create a test quote entry in database if needed
    echo "🔗 Testing admin notification system...\n";
    
    // Test simple email
    $to = 'phil.bajenski@gmail.com';
    $subject = '🧪 Test Admin Notification - ' . date('H:i:s');
    $message = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <h2 style='color: #2c5f2d;'>🧪 Email System Test</h2>
    <p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>
    <div style='background: #f0f8f0; padding: 15px; border-left: 4px solid #2c5f2d; margin: 20px 0;'>
        <h3 style='color: #2c5f2d; margin-top: 0;'>✅ Admin Email System Status</h3>
        <ul>
            <li><strong>Config:</strong> Loaded successfully</li>
            <li><strong>Admin Email:</strong> phil.bajenski@gmail.com</li>
            <li><strong>System:</strong> Ready for notifications</li>
        </ul>
    </div>
    <p><strong>🎯 This test confirms:</strong></p>
    <ul>
        <li>Email system is functioning</li>
        <li>Admin notifications can be sent</li>
        <li>Configuration is correct</li>
    </ul>
    <p>If you receive this email, admin notifications are working!</p>
    <p><strong>🌲 Carpe Tree System Test</strong></p>
</body>
</html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Carpe Tree System <noreply@carpetree.com>\r\n";
    $headers .= "Reply-To: phil.bajenski@gmail.com\r\n";

    echo "📤 Sending test admin notification to $to...\n";

    if (mail($to, $subject, $message, $headers)) {
        echo "✅ SUCCESS! Test admin notification sent\n";
        echo "📬 Check your Gmail inbox for the test email\n";
    } else {
        echo "❌ FAILED! Admin notification could not be sent\n";
    }

} catch (Exception $e) {
    echo "❌ Error testing admin notification: " . $e->getMessage() . "\n";
}

echo "\n🔍 Quote Submission Endpoint Analysis:\n";
echo "=====================================\n";

$endpoints = [
    'submitQuote.php' => 'Main endpoint',
    'submitQuote-reliable.php' => 'Reliable fallback',  
    'submitQuote-working.php' => 'Working fallback'
];

foreach ($endpoints as $endpoint => $description) {
    echo "📡 $description ($endpoint):\n";
    
    // Check if endpoint calls admin notification
    $file_path = "server/api/$endpoint";
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        if (strpos($content, 'admin-notification') !== false) {
            echo "  ✅ Has admin notification code\n";
        } else {
            echo "  ❌ Missing admin notification code\n";
        }
        
        if (strpos($content, 'phil.bajenski@gmail.com') !== false) {
            echo "  ✅ Contains phil.bajenski@gmail.com\n";
        } else {
            echo "  ❌ Missing phil.bajenski@gmail.com\n";
        }
    } else {
        echo "  ❌ File not found\n";
    }
    echo "\n";
}

echo "🎯 Recommendations:\n";
echo "1. Check Gmail spam folder for test email\n";
echo "2. Submit a test quote and watch for admin notification\n";
echo "3. Check server logs for email errors\n";
echo "4. Verify all submit endpoints call admin notifications\n";
?>