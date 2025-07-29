<?php
require_once 'server/config/config.php';

echo "Testing OpenAI API...\n";

$url = 'https://api.openai.com/v1/chat/completions';
$data = [
    'model' => 'gpt-4o',
    'messages' => [
        ['role' => 'user', 'content' => 'Say "Hello World"']
    ],
    'max_tokens' => 10
];

$headers = [
    'Authorization: Bearer ' . $OPENAI_API_KEY,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?> 