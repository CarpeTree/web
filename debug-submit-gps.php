<?php
// Debug GPS submission
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate form data
$_POST = [
    'email' => 'debug.gps@example.com',
    'name' => 'Debug GPS Customer',
    'phone' => '778-555-DEBUG',
    'address' => 'Debug Address, BC',
    'selectedServices' => '["tree_assessment"]',
    'notes' => 'Debug GPS test',
    'geo_latitude' => '49.2827',
    'geo_longitude' => '-123.1207',
    'geo_accuracy' => '10.0'
];

try {
    // Include the actual submitQuote logic
    ob_start();
    include __DIR__ . '/server/api/submitQuote.php';
    $output = ob_get_clean();
    
    if (empty($output)) {
        echo json_encode(['debug' => 'Script completed with no output']);
    } else {
        echo $output;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'debug_error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>