<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Override the exception handler to get a full stack trace
set_exception_handler(function($exception) {
    echo "=== EXCEPTION CAUGHT ===\n";
    echo "Message: " . $exception->getMessage() . "\n";
    echo "File: " . $exception->getFile() . "\n";
    echo "Line: " . $exception->getLine() . "\n";
    echo "Stack Trace:\n" . $exception->getTraceAsString() . "\n";
});

// Simulate the exact submission
$_POST = [
    'email' => 'trace@example.com',
    'name' => 'Trace Test User',
    'phone' => '555-0000',
    'address' => '000 Trace St, Nelson, BC',
    'selectedServices' => '["pruning"]',
    'notes' => 'Trace test'
];

// Start output buffering to capture any output
ob_start();

try {
    // Include the submitQuote.php to trigger the exact same flow
    include 'server/api/submitQuote.php';
} catch (Exception $e) {
    echo "Exception during include: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo "=== OUTPUT ===\n";
echo $output;
?> 