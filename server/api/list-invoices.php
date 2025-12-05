<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../utils/invoice-utils.php';

$quoteId = isset($_GET['quote_id']) ? (int) $_GET['quote_id'] : null;
if ($quoteId !== null && $quoteId <= 0) {
    $quoteId = null;
}

try {
    $invoices = list_invoices($quoteId);
    echo json_encode([
        'success' => true,
        'invoices' => $invoices
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
