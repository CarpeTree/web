<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../utils/invoice-utils.php';

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

$now = date('c');
$statusHistory = isset($record['status_history']) && is_array($record['status_history']) ? $record['status_history'] : [];
$lastStatus = end($statusHistory);
if (!isset($lastStatus['status']) || $lastStatus['status'] !== 'agreed') {
    $statusHistory[] = [
        'status' => 'agreed',
        'timestamp' => $now,
        'source' => 'customer_accept'
    ];
}

$record['status'] = 'agreed';
$record['status_history'] = $statusHistory;
$record['customer_acceptance'] = [
    'timestamp' => $now,
    'selected_services' => isset($input['selected_services']) && is_array($input['selected_services']) ? $input['selected_services'] : [],
    'final_total' => isset($input['final_total']) ? (float) $input['final_total'] : null
];
$record['saved_at'] = $now;

$existingInvoiceId = isset($record['invoice_ids']) && is_array($record['invoice_ids']) ? end($record['invoice_ids']) : null;
$invoice = generateInvoiceForQuote($quoteId, $record, $existingInvoiceId ?: null);
if ($invoice) {
    if (!isset($record['invoice_ids']) || !is_array($record['invoice_ids'])) {
        $record['invoice_ids'] = [];
    }
    if (!$existingInvoiceId || $existingInvoiceId !== $invoice['invoice_id']) {
        $record['invoice_ids'][] = $invoice['invoice_id'];
    }
    $record['last_invoice_generated_at'] = $invoice['created_at'] ?? $now;
}

if (!isset($record['totals'])) {
    $record['totals'] = compute_invoice_totals($record['items'] ?? []);
}

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
    $stmt->execute(['agreed', $quoteId]);
} catch (Throwable $e) {
    // Non fatal
}

echo json_encode([
    'success' => true,
    'invoice_id' => $invoice['invoice_id'] ?? null
]);
