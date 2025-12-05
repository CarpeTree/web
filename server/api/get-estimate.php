<?php
header('Content-Type: application/json');

$quoteId = isset($_GET['quote_id']) ? (int) $_GET['quote_id'] : 0;
if ($quoteId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'quote_id required']);
    exit;
}

$path = __DIR__ . '/../storage/estimates/quote_' . $quoteId . '.json';
if (!is_file($path)) {
    echo json_encode(['ok' => true, 'record' => null]);
    exit;
}

$contents = file_get_contents($path);
$decoded = json_decode($contents, true);
if (!is_array($decoded)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'estimate file corrupt']);
    exit;
}

echo json_encode([
    'ok' => true,
    'record' => $decoded
]);
