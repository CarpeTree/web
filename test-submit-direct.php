<?php
// Direct test of quote submission to see exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== TESTING SUBMITQUOTE.PHP DIRECTLY ===\n\n";

// Simulate POST data
$_POST = [
    'email' => 'test@example.com',
    'name' => 'Test User',
    'phone' => '7786553741',
    'address' => 'Test Address',
    'selectedServices' => '["sprinklers","fuel_modification"]',
    'notes' => 'Test submission'
];

// Simulate empty file upload to avoid that error
$_FILES = [
    'files' => [
        'tmp_name' => [''],
        'name' => ['test.jpg'],
        'size' => [1000],
        'error' => [UPLOAD_ERR_NO_FILE],
        'type' => ['image/jpeg']
    ]
];

echo "POST data simulated\n";
echo "FILES data simulated\n\n";

try {
    // Include the actual submitQuote.php
    include 'server/api/submitQuote.php';
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "PHP ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 