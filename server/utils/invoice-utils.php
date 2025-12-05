<?php
require_once __DIR__ . '/../config/config.php';

function invoice_storage_dir(): string {
    $dir = __DIR__ . '/../storage/invoices';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function invoice_path(string $invoiceId): string {
    return invoice_storage_dir() . '/' . $invoiceId . '.json';
}

function load_invoice(string $invoiceId): ?array {
    $path = invoice_path($invoiceId);
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function save_invoice(array $invoice): bool {
    if (empty($invoice['invoice_id'])) {
        return false;
    }
    $invoice['updated_at'] = date('c');
    return (bool) file_put_contents(
        invoice_path($invoice['invoice_id']),
        json_encode($invoice, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function compute_invoice_totals(array $items): array {
    $serviceSubtotal = 0.0;
    $logisticsTotal = 0.0;
    $discountTotal = 0.0;

    foreach ($items as $item) {
        if (empty($item['selected'])) continue;
        $price = (float) ($item['price_cad'] ?? 0);
        if ($price < 0) {
            $discountTotal += abs($price);
            continue;
        }
        $sourceType = strtolower((string) ($item['source_type'] ?? $item['source_model'] ?? ''));
        if (preg_match('/travel|logistics|disposal|global/', $sourceType)) {
            $logisticsTotal += $price;
        } else {
            $serviceSubtotal += $price;
        }
    }

    $taxable = max(0, $serviceSubtotal + $logisticsTotal - $discountTotal);
    $tax = round($taxable * 0.12, 2);
    $total = round($taxable + $tax, 2);

    return [
        'services_subtotal' => round($serviceSubtotal, 2),
        'logistics_total'   => round($logisticsTotal, 2),
        'discount_total'    => round($discountTotal, 2),
        'tax'               => $tax,
        'total'             => $total
    ];
}

function generate_invoice_id(int $quoteId): string {
    return sprintf('INV-%d-%s', $quoteId, date('YmdHis'));
}

function generateInvoiceForQuote(int $quoteId, array $estimateRecord, ?string $existingInvoiceId = null): ?array {
    $items = isset($estimateRecord['items']) && is_array($estimateRecord['items'])
        ? $estimateRecord['items'] : [];
    $selectedItems = array_values(array_filter($items, function ($item) {
        return !empty($item['selected']);
    }));

    $totals = compute_invoice_totals($items);
    $now = date('c');
    $dueDate = date('c', strtotime('+14 days'));

    $invoiceId = $existingInvoiceId ?: generate_invoice_id($quoteId);
    $invoice = $existingInvoiceId ? load_invoice($existingInvoiceId) : null;
    if (!$invoice) {
        $invoice = [
            'invoice_id' => $invoiceId,
            'quote_id' => $quoteId,
            'created_at' => $now,
            'due_date' => $dueDate,
            'status' => 'draft',
            'status_history' => [
                ['status' => 'draft', 'timestamp' => $now, 'source' => 'system']
            ]
        ];
    }

    $invoice['line_items'] = $selectedItems;
    $invoice['totals'] = $totals;
    $invoice['estimate_snapshot'] = [
        'saved_at' => $estimateRecord['saved_at'] ?? $now,
        'status' => $estimateRecord['status'] ?? 'agreed'
    ];

    // Fetch customer information
    try {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            require_once __DIR__ . '/../config/database-simple.php';
        }
        $stmt = $pdo->prepare("SELECT c.name, c.email, c.address FROM quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = ? LIMIT 1");
        $stmt->execute([$quoteId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invoice['customer'] = [
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'address' => $row['address'] ?? ''
            ];
        }
    } catch (Throwable $e) {
        // ignore DB errors
    }

    if (!isset($invoice['customer'])) {
        $invoice['customer'] = [
            'name' => '',
            'email' => '',
            'address' => ''
        ];
    }

    if (!save_invoice($invoice)) {
        return null;
    }

    return $invoice;
}

function list_invoices(?int $quoteId = null): array {
    $dir = invoice_storage_dir();
    $pattern = $dir . '/*.json';
    $files = glob($pattern) ?: [];
    $invoices = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) continue;
        if ($quoteId !== null && (int)($data['quote_id'] ?? 0) !== $quoteId) continue;
        $invoices[] = $data;
    }
    usort($invoices, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $invoices;
}

function mark_invoice_status(string $invoiceId, string $status, array $meta = []): ?array {
    $invoice = load_invoice($invoiceId);
    if (!$invoice) return null;

    $now = date('c');
    if (!isset($invoice['status_history']) || !is_array($invoice['status_history'])) {
        $invoice['status_history'] = [];
    }

    $invoice['status'] = $status;
    $invoice['status_history'][] = [
        'status' => $status,
        'timestamp' => $now,
        'source' => $meta['source'] ?? 'system'
    ];

    if (!isset($invoice['delivery'])) {
        $invoice['delivery'] = [];
    }
    $channels = (isset($meta['channels']) && is_array($meta['channels'])) ? $meta['channels'] : [];
    if (!isset($invoice['delivery']['events']) || !is_array($invoice['delivery']['events'])) {
        $invoice['delivery']['events'] = [];
    }
    $invoice['delivery']['events'][] = [
        'timestamp' => $now,
        'channels' => $channels,
        'notes' => $meta['notes'] ?? null,
        'source' => $meta['source'] ?? 'system'
    ];
    $invoice['delivery']['last_channels'] = $channels;
    if (isset($meta['notes'])) {
        $invoice['delivery']['last_notes'] = $meta['notes'];
    }
    $invoice['delivery']['last_updated'] = $now;

    if (!save_invoice($invoice)) {
        return null;
    }

    return $invoice;
}
