<?php
// Test quote submission endpoint directly
header('Content-Type: text/plain');

echo "=== TESTING QUOTE SUBMISSION ===\n\n";

// Test data
$test_data = [
    'name' => 'Test Customer',
    'email' => 'test@example.com',
    'phone' => '778-555-1234',
    'address' => 'Test Address',
    'selectedServices' => '["pruning","assessment"]',
    'notes' => 'Test submission from diagnostic script',
    'referralSource' => 'website',
    'newsletterOptIn' => 'false'
];

echo "Test data prepared:\n";
print_r($test_data);

// Test 1: Direct PHP file execution
echo "\n=== TEST 1: Direct file check ===\n";
$submit_file = 'server/api/submitQuote.php';
if (file_exists($submit_file)) {
    echo "✅ submitQuote.php exists\n";
    echo "File size: " . filesize($submit_file) . " bytes\n";
} else {
    echo "❌ submitQuote.php not found!\n";
}

// Test 2: Database connection
echo "\n=== TEST 2: Database connection ===\n";
try {
    require_once 'server/config/database-simple.php';
    echo "✅ Database connection successful\n";
    
    // Check if tables exist
    $tables_to_check = ['customers', 'quotes'];
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' missing\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Test 3: Simulate POST request
echo "\n=== TEST 3: Simulate POST request ===\n";

// Backup original $_POST
$original_post = $_POST;
$original_method = $_SERVER['REQUEST_METHOD'];

// Set up test environment
$_POST = $test_data;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture output
ob_start();
$error_occurred = false;

try {
    include 'server/api/submitQuote.php';
} catch (Exception $e) {
    $error_occurred = true;
    echo "PHP Error: " . $e->getMessage() . "\n";
} catch (Error $e) {
    $error_occurred = true;
    echo "PHP Fatal Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

// Restore original values
$_POST = $original_post;
$_SERVER['REQUEST_METHOD'] = $original_method;

echo "Output from submitQuote.php:\n";
echo "=============================\n";
echo $output;
echo "=============================\n";

if ($error_occurred) {
    echo "❌ Errors occurred during execution\n";
} else {
    echo "✅ Script executed without fatal errors\n";
}

// Test 4: Check for common issues
echo "\n=== TEST 4: Common issues check ===\n";

// Check PHP error log
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "PHP error log location: $error_log\n";
    $recent_errors = shell_exec("tail -n 10 '$error_log' 2>/dev/null");
    if ($recent_errors) {
        echo "Recent PHP errors:\n$recent_errors\n";
    }
} else {
    echo "No accessible PHP error log found\n";
}

// Check file permissions
$submit_file_perms = substr(sprintf('%o', fileperms($submit_file)), -4);
echo "submitQuote.php permissions: $submit_file_perms\n";

echo "\n=== DIAGNOSIS COMPLETE ===\n";
?> 