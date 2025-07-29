<?php
// Comprehensive Email System Diagnostic Tool
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Email System Comprehensive Diagnostic</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .test{margin:15px 0;padding:10px;border:1px solid #ddd;} .success{background:#d4edda;} .error{background:#f8d7da;} .warning{background:#fff3cd;}</style>";

// Test 1: Configuration Check
echo "<div class='test'>";
echo "<h3>📋 Test 1: Configuration Check</h3>";

require_once __DIR__ . '/server/config/config.php';
require_once __DIR__ . '/server/config/database-simple.php';

echo "<strong>Email Configuration:</strong><br>";
echo "SMTP Host: " . ($SMTP_HOST ?? 'NOT SET') . "<br>";
echo "SMTP Port: " . ($SMTP_PORT ?? 'NOT SET') . "<br>";  
echo "SMTP User: " . ($SMTP_USER ?? 'NOT SET') . "<br>";
echo "SMTP From: " . ($SMTP_FROM ?? 'NOT SET') . "<br>";
echo "Admin Email: " . ($ADMIN_EMAIL ?? 'NOT SET') . "<br>";
echo "OpenAI Key: " . (empty($OPENAI_API_KEY) ? 'NOT SET' : 'SET (' . strlen($OPENAI_API_KEY) . ' chars)') . "<br>";

if (empty($SMTP_HOST) || empty($SMTP_USER) || empty($ADMIN_EMAIL)) {
    echo "<div class='error'>❌ Missing required email configuration!</div>";
} else {
    echo "<div class='success'>✅ Email configuration looks complete</div>";
}
echo "</div>";

// Test 2: Database Connection & Recent Quotes
echo "<div class='test'>";
echo "<h3>📊 Test 2: Database & Recent Quotes</h3>";

try {
    $stmt = $pdo->prepare("SELECT q.id, q.created_at, c.email, c.name FROM quotes q JOIN customers c ON q.customer_id = c.id ORDER BY q.created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Recent Quotes:</strong><br>";
    if (empty($recent_quotes)) {
        echo "<div class='warning'>⚠️ No quotes found in database</div>";
    } else {
        foreach ($recent_quotes as $quote) {
            echo "Quote #{$quote['id']} - {$quote['name']} ({$quote['email']}) - {$quote['created_at']}<br>";
        }
        echo "<div class='success'>✅ Database connection working, " . count($recent_quotes) . " recent quotes found</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: PHPMailer Test
echo "<div class='test'>";
echo "<h3>✉️ Test 3: PHPMailer Direct Test</h3>";

try {
    require_once __DIR__ . '/server/utils/mailer.php';
    
    $test_result = sendEmail(
        $ADMIN_EMAIL,
        'CarpeTree Email System Test',
        'This is a test email from the CarpeTree diagnostic tool. If you receive this, the email system is working!',
        '<h2>Email System Test</h2><p>This is a <strong>test email</strong> from the CarpeTree diagnostic tool.</p><p>If you receive this, the email system is working! ✅</p>',
        null // No quote_id for test
    );
    
    if ($test_result) {
        echo "<div class='success'>✅ Test email sent successfully to: $ADMIN_EMAIL</div>";
    } else {
        echo "<div class='error'>❌ Failed to send test email</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ PHPMailer error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Admin Notification Function Test
echo "<div class='test'>";
echo "<h3>🔔 Test 4: Admin Notification Function</h3>";

if (!empty($recent_quotes)) {
    $test_quote_id = $recent_quotes[0]['id'];
    echo "Testing with Quote ID: $test_quote_id<br>";
    
    try {
        require_once __DIR__ . '/server/api/admin-notification.php';
        
        $admin_result = sendAdminNotification($test_quote_id);
        
        if ($admin_result) {
            echo "<div class='success'>✅ Admin notification sent successfully for Quote #$test_quote_id</div>";
        } else {
            echo "<div class='error'>❌ Admin notification failed for Quote #$test_quote_id</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Admin notification error: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ No quotes available to test admin notification</div>";
}
echo "</div>";

// Test 5: Email Logs Check
echo "<div class='test'>";
echo "<h3>📝 Test 5: Email Logs Check</h3>";

try {
    $stmt = $pdo->prepare("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10");
    $stmt->execute();
    $email_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($email_logs)) {
        echo "<div class='warning'>⚠️ No email logs found - emails may not be sending</div>";
    } else {
        echo "<strong>Recent Email Activity:</strong><br>";
        foreach ($email_logs as $log) {
            $status_icon = $log['status'] === 'sent' ? '✅' : '❌';
            echo "$status_icon {$log['recipient']} - {$log['subject']} - {$log['sent_at']}<br>";
        }
        echo "<div class='success'>✅ Found " . count($email_logs) . " email log entries</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Email logs error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 6: Server Environment Check
echo "<div class='test'>";
echo "<h3>🖥️ Test 6: Server Environment</h3>";

echo "<strong>Server Info:</strong><br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Mail Function: " . (function_exists('mail') ? 'Available' : 'NOT Available') . "<br>";
echo "cURL Support: " . (function_exists('curl_init') ? 'Available' : 'NOT Available') . "<br>";
echo "OpenSSL Support: " . (extension_loaded('openssl') ? 'Available' : 'NOT Available') . "<br>";

if (!function_exists('mail')) {
    echo "<div class='error'>❌ PHP mail() function not available</div>";
} else {
    echo "<div class='success'>✅ PHP mail() function available</div>";
}
echo "</div>";

// Test 7: Quick Fix Suggestions
echo "<div class='test'>";
echo "<h3>🔧 Test 7: Quick Fix Suggestions</h3>";

echo "<strong>Common Issues & Solutions:</strong><br>";
echo "1. <strong>Wrong Admin Email:</strong> Currently set to '$ADMIN_EMAIL' - should this be 'phil.bajenski@gmail.com'?<br>";
echo "2. <strong>Typo in Email:</strong> 'sapport@carpetree.com' should be 'support@carpetree.com'<br>";
echo "3. <strong>SMTP Issues:</strong> iCloud SMTP may have restrictions<br>";
echo "4. <strong>Spam Folder:</strong> Check spam/junk folder<br>";
echo "5. <strong>Email Limits:</strong> Some providers limit automated emails<br>";

echo "<br><strong>Recommended Actions:</strong><br>";
echo "• Update admin email to your actual Gmail: phil.bajenski@gmail.com<br>";
echo "• Fix typo: sapport → support<br>";
echo "• Test with a different SMTP provider if needed<br>";
echo "• Check email server logs<br>";

echo "</div>";

echo "<hr>";
echo "<p><strong>🎯 Next Steps:</strong> Review the results above and address any failed tests. If emails are still not working, the issue is likely in the SMTP configuration or email provider restrictions.</p>";
?> 