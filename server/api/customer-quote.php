<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database-simple.php';

$quoteId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($quoteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'quote_id required']);
    exit;
}

$estimateDir = __DIR__ . '/../storage/estimates';
$estimatePath = $estimateDir . '/quote_' . $quoteId . '.json';
if (!is_file($estimatePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Estimate not found. Ask admin to prepare estimate first.']);
    exit;
}

$record = json_decode(file_get_contents($estimatePath), true);
if (!is_array($record)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Invalid estimate file']);
    exit;
}

$statusLabels = [
    'draft' => 'Draft',
    'ready' => 'Ready to Send',
    'sent' => 'Sent to Customer',
    'agreed' => 'Agreed / Approved',
    'lost' => 'Closed - Not Proceeding',
    'revision_requested' => 'Revision Requested'
];

$services = [];
$logisticsItems = [];
$serviceSubtotal = 0.0;
$logisticsTotal = 0.0;
$discountTotal = 0.0;
$items = isset($record['items']) && is_array($record['items']) ? $record['items'] : [];

foreach ($items as $item) {
    $selected = !empty($item['selected']);
    $price = (float) ($item['price_cad'] ?? 0);
    $sourceType = strtolower((string) ($item['source_type'] ?? $item['source_model'] ?? 'analysis'));
    $isLogistics = preg_match('/travel|logistics|disposal|global/', $sourceType) === 1;

    if ($selected) {
        if ($price < 0) {
            $discountTotal += abs($price);
        } elseif ($isLogistics) {
            $logisticsTotal += $price;
        } else {
            $serviceSubtotal += $price;
        }
    }

    $servicePayload = [
        'name' => (string) ($item['name'] ?? 'Service'),
        'tree' => (string) ($item['tree'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'price' => $price,
        'selected' => $selected,
        'optional' => isset($item['optional']) ? (bool) $item['optional'] : !$selected,
        'explanation' => $item['explanation'] ?? null,
        'source_type' => $sourceType,
        'locked' => $isLogistics,
        'meta' => $item['meta'] ?? null
    ];

    if ($isLogistics) {
        $logisticsItems[] = $servicePayload;
    }

    $services[] = $servicePayload;
}

$taxableAmount = max(0, $serviceSubtotal + $logisticsTotal - $discountTotal);
$tax = round($taxableAmount * 0.12, 2);
$total = $taxableAmount + $tax;

$quoteInfo = [
    'id' => $quoteId,
    'customer_name' => '',
    'address' => '',
    'customer_email' => '',
    'distance_km' => null,
    'analysis_method' => 'AI-assisted estimate',
    'status' => $record['status'] ?? 'draft',
    'status_label' => $statusLabels[$record['status'] ?? 'draft'] ?? 'Draft',
    'services' => $services,
    'logistics' => $logisticsItems,
    'subtotal' => round($serviceSubtotal, 2),
    'travel_cost' => round($logisticsTotal, 2),
    'discount' => round($discountTotal, 2),
    'tax' => $tax,
    'total' => round($total, 2),
    'change_requests' => isset($record['change_requests']) && is_array($record['change_requests']) ? $record['change_requests'] : [],
    'selection_history' => isset($record['selection_history']) && is_array($record['selection_history']) ? $record['selection_history'] : [],
    'status_history' => isset($record['status_history']) && is_array($record['status_history']) ? $record['status_history'] : []
];

try {
    $stmt = $pdo->prepare("SELECT q.id, q.quote_status, q.notes, q.distance_km, q.travel_cost, c.name AS customer_name, c.address, c.email
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ? LIMIT 1");
    $stmt->execute([$quoteId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $quoteInfo['customer_name'] = $row['customer_name'] ?? $quoteInfo['customer_name'];
        $quoteInfo['address'] = $row['address'] ?? $quoteInfo['address'];
        $quoteInfo['customer_email'] = $row['email'] ?? $quoteInfo['customer_email'];
        if (!empty($row['distance_km'])) {
            $quoteInfo['distance_km'] = (float) $row['distance_km'];
        }
        if (!empty($row['quote_status']) && empty($record['status'])) {
            $quoteInfo['status'] = $row['quote_status'];
            $quoteInfo['status_label'] = $statusLabels[$row['quote_status']] ?? ucfirst(str_replace('_', ' ', $row['quote_status']));
        }
    }
} catch (Throwable $e) {
    // Non-fatal; return estimate data without DB extras.
}

echo json_encode([
    'success' => true,
    'quote' => $quoteInfo
]);
