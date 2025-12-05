<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$quoteId = isset($input['quote_id']) ? (int) $input['quote_id'] : 0;
if ($quoteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'quote_id required']);
    exit;
}

$estimateDir = __DIR__ . '/../storage/estimates';
$estimatePath = $estimateDir . '/quote_' . $quoteId . '.json';
if (!is_file($estimatePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Estimate not found']);
    exit;
}

$record = json_decode(file_get_contents($estimatePath), true);
if (!is_array($record)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid estimate record']);
    exit;
}

$serviceName = (string) ($input['service_name'] ?? '');
$tree = (string) ($input['tree'] ?? '');
$selected = !empty($input['selected']);
$action = $input['action'] ?? ($selected ? 'added' : 'removed');
$optional = !empty($input['optional']);
$price = (float) ($input['price'] ?? 0);
$sourceType = (string) ($input['source_type'] ?? 'analysis');

$now = date('c');
if (!isset($record['selection_history']) || !is_array($record['selection_history'])) {
    $record['selection_history'] = [];
}
$record['selection_history'][] = [
    'timestamp' => $now,
    'service_name' => $serviceName,
    'tree' => $tree,
    'action' => $action,
    'optional' => $optional,
    'selected' => $selected,
    'price' => $price,
    'source_type' => $sourceType
];

$matched = false;
if (isset($record['items']) && is_array($record['items'])) {
    foreach ($record['items'] as &$item) {
        $itemName = (string) ($item['name'] ?? '');
        $itemTree = (string) ($item['tree'] ?? '');
        if ($itemName === $serviceName && $itemTree === $tree) {
            $item['selected'] = $selected;
            $item['optional'] = $optional;
            if (!empty($sourceType)) {
                $item['source_type'] = $sourceType;
            }
            if (isset($input['meta']) && is_array($input['meta'])) {
                $item['meta'] = $input['meta'];
            }
            $matched = true;
            break;
        }
    }
    unset($item);
}

if (!$matched) {
    $record['items'][] = [
        'name' => $serviceName,
        'tree' => $tree,
        'selected' => $selected,
        'optional' => $optional,
        'price_cad' => $price,
        'source_model' => $sourceType,
        'source_type' => $sourceType
    ];
}

$record['saved_at'] = $now;

if (!is_dir($estimateDir)) {
    @mkdir($estimateDir, 0775, true);
}

if (!file_put_contents($estimatePath, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to persist selection']);
    exit;
}

echo json_encode(['success' => true]);
