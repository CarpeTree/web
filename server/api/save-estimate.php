<?php
header('Content-Type: application/json');

// Persist estimate selections as a JSON file (non-destructive).
// If DB is available, we could later mirror to quote_services, but schema is out-of-scope here.

require_once __DIR__ . '/../utils/invoice-utils.php';

function read_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $in = read_json_body();
    $quoteId = isset($in['quote_id']) ? intval($in['quote_id']) : 0;
    $items = isset($in['items']) && is_array($in['items']) ? $in['items'] : [];
    if ($quoteId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'quote_id required']);
        exit;
    }

    $dir = __DIR__ . '/../storage/estimates';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (!is_dir($dir)) {
        echo json_encode(['ok' => false, 'error' => 'cannot create storage directory']);
        exit;
    }

    $path = $dir . '/quote_' . $quoteId . '.json';
    $existing = [];
    if (is_file($path)) {
        $decoded = json_decode(file_get_contents($path), true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    $allowedStatus = ['draft','ready','sent','agreed','lost','revision_requested'];
    $status = isset($in['status']) && in_array($in['status'], $allowedStatus, true)
        ? $in['status']
        : ($existing['status'] ?? 'draft');

    $statusHistory = [];
    if (!empty($existing['status_history']) && is_array($existing['status_history'])) {
        $statusHistory = $existing['status_history'];
    }
    $timestamp = date('c');
    if (empty($statusHistory)) {
        $statusHistory[] = [
            'status' => $status,
            'timestamp' => $timestamp,
            'source' => 'initial'
        ];
    } else {
        $last = end($statusHistory);
        if (!isset($last['status']) || $last['status'] !== $status) {
            $statusHistory[] = [
                'status' => $status,
                'timestamp' => $timestamp,
                'source' => 'update'
            ];
        }
    }

    $normalizedItems = array_map(function($it){
        return [
            'selected' => !empty($it['selected']),
            'name' => (string)($it['name'] ?? ''),
            'tree' => (string)($it['tree'] ?? ''),
            'description' => (string)($it['description'] ?? ''),
            'price_cad' => (float)($it['price_cad'] ?? 0),
            'source_model' => (string)($it['source_model'] ?? 'estimator'),
            'source_type' => (string)($it['source_type'] ?? ($it['source_model'] ?? 'estimator')),
            'optional' => !empty($it['optional']),
            'meta' => is_array($it['meta'] ?? null) ? $it['meta'] : ($it['meta'] ?? null)
        ];
    }, $items);

    $totals = compute_invoice_totals($normalizedItems);

    $record = [
        'quote_id' => $quoteId,
        'saved_at' => $timestamp,
        'status' => $status,
        'status_history' => $statusHistory,
        'items' => $normalizedItems,
        'totals' => $totals,
        'change_requests' => (!empty($existing['change_requests']) && is_array($existing['change_requests'])) ? $existing['change_requests'] : [],
        'selection_history' => (!empty($existing['selection_history']) && is_array($existing['selection_history'])) ? $existing['selection_history'] : [],
        'customer_acceptance' => $existing['customer_acceptance'] ?? null,
        'invoice_ids' => (!empty($existing['invoice_ids']) && is_array($existing['invoice_ids'])) ? $existing['invoice_ids'] : []
    ];

    $generatedInvoiceId = null;
    if ($status === 'agreed') {
        $existingInvoiceId = end($record['invoice_ids']);
        $invoice = generateInvoiceForQuote($quoteId, $record, $existingInvoiceId ?: null);
        if ($invoice) {
            $generatedInvoiceId = $invoice['invoice_id'];
            if (!$existingInvoiceId || $existingInvoiceId !== $generatedInvoiceId) {
                $record['invoice_ids'][] = $generatedInvoiceId;
            }
            $record['last_invoice_generated_at'] = $invoice['created_at'] ?? $timestamp;
        }
    }

    $ok = (bool)file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    if (!$ok) {
        echo json_encode(['ok' => false, 'error' => 'write failed']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'saved' => basename($path),
        'count' => count($record['items']),
        'status' => $status,
        'status_history' => $statusHistory,
        'saved_at' => $timestamp,
        'invoice_id' => $generatedInvoiceId,
        'invoice_ids' => $record['invoice_ids'],
        'totals' => $totals
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>







