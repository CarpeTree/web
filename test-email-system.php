<?php
// Test email system functionality
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== EMAIL SYSTEM TEST ===\n\n";

try {
    // Check if mailer exists and can be loaded
    $mailer_path = __DIR__ . '/server/utils/mailer.php';
    echo "Mailer path: $mailer_path\n";
    echo "Mailer exists: " . (file_exists($mailer_path) ? 'YES' : 'NO') . "\n";
    
    if (!file_exists($mailer_path)) {
        echo "❌ Mailer file not found\n";
        exit;
    }
    
    // Try to include mailer
    try {
        require_once $mailer_path;
        echo "✅ Mailer loaded successfully\n";
    } catch (Exception $e) {
        echo "❌ Error loading mailer: " . $e->getMessage() . "\n";
        exit;
    }
    
    // Check if sendEmail function exists
    if (function_exists('sendEmail')) {
        echo "✅ sendEmail function available\n";
    } else {
        echo "❌ sendEmail function not found\n";
        exit;
    }
    
    // Check email template
    $template_path = __DIR__ . '/server/templates/quote_confirmation.html';
    echo "\nEmail template path: $template_path\n";
    echo "Template exists: " . (file_exists($template_path) ? 'YES' : 'NO') . "\n";
    
    // Check configuration
    echo "\n=== EMAIL CONFIGURATION CHECK ===\n";
    $config_path = __DIR__ . '/server/config/config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
        echo "Config file loaded\n";
        
        $smtp_vars = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_PORT', 'SMTP_FROM'];
        foreach ($smtp_vars as $var) {
            if (isset($$var)) {
                echo "$var: " . ($var === 'SMTP_PASS' ? '[HIDDEN]' : $$var) . "\n";
            } else {
                echo "$var: NOT SET\n";
            }
        }
    } else {
        echo "❌ Config file not found\n";
    }
    
    // Test sending a simple email
    echo "\n=== SENDING TEST EMAIL ===\n";
    
    $test_email_data = [
        'customer_name' => 'Phil (Test)',
        'quote_id' => 1,
        'services' => '["removal"]',
        'files_count' => 0,
        'has_files' => false
    ];
    
    echo "Attempting to send test email to phil.bajenski@gmail.com...\n";
    
    try {
        $email_result = sendEmail(
            'phil.bajenski@gmail.com',
            'Test Quote Confirmation - Carpe Tree\'em',
            'quote_confirmation',
            $test_email_data
        );
        
        if ($email_result) {
            echo "✅ Email sent successfully!\n";
        } else {
            echo "❌ Email failed to send\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Email error: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    // Check email log after attempt
    echo "\n=== CHECKING EMAIL LOG AFTER SEND ===\n";
    $stmt = $pdo->prepare("SELECT * FROM email_log ORDER BY sent_at DESC LIMIT 3");
    $stmt->execute();
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo "❌ Still no emails in log\n";
    } else {
        echo "✅ Found " . count($emails) . " email log entries:\n";
        foreach ($emails as $email) {
            echo "  To: {$email['recipient_email']}\n";
            echo "  Subject: {$email['subject']}\n";
            echo "  Status: {$email['status']}\n";
            echo "  Sent: {$email['sent_at']}\n";
            if ($email['error_message']) {
                echo "  Error: {$email['error_message']}\n";
            }
            echo "  ---\n";
        }
    }
    
    // Test PHP mail function directly
    echo "\n=== TESTING PHP MAIL FUNCTION ===\n";
    $mail_result = mail(
        'phil.bajenski@gmail.com',
        'Direct PHP Mail Test - Carpe Tree\'em',
        'This is a test email sent directly using PHP mail() function.',
        'From: noreply@carpetree.com'
    );
    
    if ($mail_result) {
        echo "✅ PHP mail() function works\n";
    } else {
        echo "❌ PHP mail() function failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 