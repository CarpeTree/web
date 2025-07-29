<?php
// Debug AI distance calculator
echo "Debugging AI Distance Calculator...\n";
echo "==================================\n\n";

require_once __DIR__ . '/server/config/config.php';

// Check API key
echo "🔑 Checking OpenAI API Key...\n";
if (empty($OPENAI_API_KEY)) {
    echo "❌ ERROR: OpenAI API key not configured!\n";
    exit;
} else {
    echo "✅ API Key configured: " . substr($OPENAI_API_KEY, 0, 10) . "...\n\n";
}

// Test simple API call
echo "🤖 Testing OpenAI API connection...\n";

$data = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Calculate driving distance from Nelson, BC to Fernie, BC in kilometers. Return only: {"distance_km": number}'
        ]
    ],
    'max_tokens' => 100,
    'temperature' => 0.1
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $OPENAI_API_KEY,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "HTTP Code: $http_code\n";
if ($error) {
    echo "cURL Error: $error\n";
}

if ($response) {
    echo "Raw Response:\n";
    echo $response . "\n\n";
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo "✅ AI Response Content:\n";
        echo $result['choices'][0]['message']['content'] . "\n";
    } elseif (isset($result['error'])) {
        echo "❌ API Error:\n";
        echo json_encode($result['error'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ No response received\n";
}
?> 