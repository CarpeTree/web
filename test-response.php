<?php
// Test what submitQuote.php is actually returning
header('Content-Type: text/plain');

echo "=== TESTING SUBMITQUOTE RESPONSE ===\n\n";

// Test the actual endpoint
$url = 'https://carpetree.com/server/api/submitQuote.php';
$data = [
    'email' => 'test@example.com',
    'name' => 'Test User',
    'phone' => '7786553741',
    'address' => 'Test Address',
    'selectedServices' => '["sprinklers"]',
    'notes' => 'Test submission'
];

// Create a simple test without files first
$postdata = http_build_query($data);

$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    ]
];

$context = stream_context_create($opts);

echo "Making request to: $url\n";
echo "POST data: " . print_r($data, true) . "\n";

$result = @file_get_contents($url, false, $context);

if ($result === false) {
    echo "❌ Request failed\n";
    echo "Error: " . print_r(error_get_last(), true) . "\n";
} else {
    echo "✅ Response received\n";
    echo "Response length: " . strlen($result) . " bytes\n";
    echo "Raw response:\n";
    echo "---START---\n";
    echo $result;
    echo "\n---END---\n";
    
    // Try to decode as JSON
    $json = json_decode($result, true);
    if ($json === null) {
        echo "\n❌ Not valid JSON\n";
        echo "JSON error: " . json_last_error_msg() . "\n";
    } else {
        echo "\n✅ Valid JSON:\n";
        print_r($json);
    }
}
?> 