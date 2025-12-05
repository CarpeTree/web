<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database-simple.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$quoteId = isset($input['quote_id']) ? (int) $input['quote_id'] : 0;
$message = trim((string) ($input['requested_changes'] ?? ''));
if ($quoteId <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'quote_id and requested_changes required']);
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

$now = date('c');
if (!isset($record['change_requests']) || !is_array($record['change_requests'])) {
    $record['change_requests'] = [];
}

$currentSelections = [];
if (isset($input['current_selections']) && is_array($input['current_selections'])) {
    foreach ($input['current_selections'] as $sel) {
        $currentSelections[] = [
            'name' => (string) ($sel['name'] ?? ''),
            'tree' => (string) ($sel['tree'] ?? ''),
            'price' => isset($sel['price']) ? (float) $sel['price'] : null
        ];
    }
}

$record['change_requests'][] = [
    'timestamp' => $now,
    'message' => $message,
    'selections' => $currentSelections
];

$statusHistory = isset($record['status_history']) && is_array($record['status_history']) ? $record['status_history'] : [];
$lastStatus = end($statusHistory);
if (!isset($lastStatus['status']) || $lastStatus['status'] !== 'revision_requested') {
    $statusHistory[] = [
        'status' => 'revision_requested',
        'timestamp' => $now,
        'source' => 'customer_request'
    ];
}

$record['status_history'] = $statusHistory;
$record['status'] = 'revision_requested';
$record['saved_at'] = $now;

if (!is_dir($estimateDir)) {
    @mkdir($estimateDir, 0775, true);
}

if (!file_put_contents($estimatePath, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write estimate record']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE quotes SET quote_status = ? WHERE id = ?');
    $stmt->execute(['revision_requested', $quoteId]);
} catch (Throwable $e) {
    // Ignore
}

echo json_encode(['success' => true]);
