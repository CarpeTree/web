<?php
// Test form submission programmatically
echo "Testing quote form submission...\n";

// Simulate form data
$postData = [
    'email' => 'phil.bajenski@gmail.com',
    'name' => 'Test Customer',
    'phone' => '778-555-1234',
    'address' => '123 Test Street, Vancouver, BC',
    'selectedServices' => json_encode(['tree-removal', 'pruning']),
    'notes' => 'Test submission from automated script',
    'howDidYouHear' => 'website',
    'referrerName' => ''
];

// Convert to URL-encoded format
$postFields = http_build_query($postData);

// Set up cURL to submit to the form
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://carpetree.com/server/api/submitQuote.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'None') . "\n";
echo "Response:\n$response\n";

// Try to extract just the JSON part
if (preg_match('/\{.*\}/', $response, $matches)) {
    echo "\nJSON Response:\n";
    $json = json_decode($matches[0], true);
    if ($json) {
        print_r($json);
    } else {
        echo "Failed to parse JSON: " . $matches[0] . "\n";
    }
}
?> 