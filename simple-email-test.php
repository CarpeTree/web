<?php
// Simple email test
header('Content-Type: text/plain');
echo "=== SIMPLE EMAIL TEST ===\n\n";

try {
    // Test 1: Check files exist
    echo "1. Checking required files...\n";
    $mailer_exists = file_exists(__DIR__ . '/server/utils/mailer.php');
    $config_exists = file_exists(__DIR__ . '/server/config/config.php');
    $template_exists = file_exists(__DIR__ . '/server/templates/quote_confirmation.html');
    
    echo "Mailer: " . ($mailer_exists ? 'YES' : 'NO') . "\n";
    echo "Config: " . ($config_exists ? 'YES' : 'NO') . "\n"; 
    echo "Template: " . ($template_exists ? 'YES' : 'NO') . "\n";
    
    // Test 2: Try basic PHP mail
    echo "\n2. Testing basic PHP mail...\n";
    $basic_mail = mail(
        'phil.bajenski@gmail.com',
        'Simple Test - Carpe Tree\'em',
        'This is a basic test email.',
        'From: test@carpetree.com'
    );
    echo "Basic mail result: " . ($basic_mail ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Test 3: Check database connection
    echo "\n3. Testing database...\n";
    require_once 'server/config/database-simple.php';
    echo "Database: CONNECTED\n";
    
    // Test 4: Check email log table
    $stmt = $pdo->query("DESCRIBE email_log");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Email log columns: " . implode(', ', $columns) . "\n";
    
    echo "\n4. Testing mailer include...\n";
    // Try to include mailer carefully
    $old_error_reporting = error_reporting(E_ALL);
    $old_display_errors = ini_get('display_errors');
    ini_set('display_errors', 1);
    
    ob_start();
    $mailer_included = false;
    try {
        require_once __DIR__ . '/server/utils/mailer.php';
        $mailer_included = true;
        echo "Mailer included successfully\n";
    } catch (Exception $e) {
        echo "Mailer include error: " . $e->getMessage() . "\n";
    } catch (ParseError $e) {
        echo "Mailer parse error: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "Mailer fatal error: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    echo $output;
    
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    if ($mailer_included && function_exists('sendEmail')) {
        echo "sendEmail function: AVAILABLE\n";
        
        echo "\n5. Testing email configuration...\n";
        if ($config_exists) {
            require_once __DIR__ . '/server/config/config.php';
            
            $smtp_settings = [
                'SMTP_HOST' => $SMTP_HOST ?? 'NOT SET',
                'SMTP_USER' => $SMTP_USER ?? 'NOT SET', 
                'SMTP_PASS' => isset($SMTP_PASS) ? '[HIDDEN]' : 'NOT SET',
                'SMTP_PORT' => $SMTP_PORT ?? 'NOT SET',
                'SMTP_FROM' => $SMTP_FROM ?? 'NOT SET'
            ];
            
            foreach ($smtp_settings as $key => $value) {
                echo "$key: $value\n";
            }
        }
        
    } else {
        echo "sendEmail function: NOT AVAILABLE\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?> 