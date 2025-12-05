<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../utils/invoice-utils.php';
require_once __DIR__ . '/../utils/mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$invoiceId = isset($input['invoice_id']) ? (string) $input['invoice_id'] : '';
if ($invoiceId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invoice_id required']);
    exit;
}

$channels = ['email' => true, 'sms' => false];
if (isset($input['channels']) && is_array($input['channels'])) {
    $channels['email'] = !empty($input['channels']['email']);
    $channels['sms'] = !empty($input['channels']['sms']);
}

$invoice = load_invoice($invoiceId);
if (!$invoice) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Invoice not found']);
    exit;
}

if (!isset($invoice['customer']['email']) || !filter_var($invoice['customer']['email'], FILTER_VALIDATE_EMAIL)) {
    if (!empty($channels['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid customer email is required to send invoice.']);
        exit;
    }
    $channels['email'] = false;
}

$totals = $invoice['totals'] ?? compute_invoice_totals($invoice['line_items'] ?? []);
$dueDate = $invoice['due_date'] ?? date('c', strtotime('+14 days'));
$portalUrl = ($GLOBALS['SITE_URL'] ?? 'https://carpetree.com') . '/customer-quote.html?id=' . ($invoice['quote_id'] ?? '');

if (!empty($channels['email'])) {
    $emailData = [
        'customer_name' => $invoice['customer']['name'] ?? 'there',
        'invoice_id' => $invoiceId,
        'quote_id' => $invoice['quote_id'] ?? '',
        'total_due' => number_format($totals['total'] ?? 0, 2),
        'services_subtotal' => number_format($totals['services_subtotal'] ?? 0, 2),
        'logistics_total' => number_format($totals['logistics_total'] ?? 0, 2),
        'discount_total' => number_format($totals['discount_total'] ?? 0, 2),
        'tax' => number_format($totals['tax'] ?? 0, 2),
        'due_date' => date('F j, Y', strtotime($dueDate)),
        'customer_portal_url' => $portalUrl,
        'quote_id_display' => $invoice['quote_id'] ?? ''
    ];

    $sent = sendEmail(
        $invoice['customer']['email'],
        "Carpe Tree'em Invoice {$invoiceId}",
        'invoice_notice',
        array_merge($emailData, [
            'quote_id' => $invoice['quote_id'] ?? '',
            'invoice_id' => $invoiceId
        ])
    );

    if (!$sent) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send invoice email']);
        exit;
    }
}

if (!empty($channels['sms'])) {
    $phone = $invoice['customer']['phone'] ?? null;
    if ($phone) {
        error_log("[SMS] Invoice {$invoiceId} would be sent to {$phone}");
    } else {
        error_log("[SMS] Invoice {$invoiceId} SMS requested but no phone on file");
    }
}

$invoice = mark_invoice_status($invoiceId, 'sent', [
    'channels' => $channels,
    'source' => 'admin',
    'notes' => $input['notes'] ?? null
]);

if (!$invoice) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update invoice status']);
    exit;
}

echo json_encode([
    'success' => true,
    'invoice' => $invoice
]);
