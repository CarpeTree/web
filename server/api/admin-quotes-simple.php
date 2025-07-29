<?php
// Simplified admin quotes API - no AI calls for fast loading
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database-simple.php';

try {
    // Set execution time limit
    set_time_limit(10);
    
    // Get recent quotes with basic info only
    $stmt = $pdo->prepare("
        SELECT 
            q.id,
            q.quote_status,
            q.quote_created_at,
            q.selected_services,
            c.name as customer_name,
            c.email as customer_email,
            c.phone,
            c.address
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status IN ('submitted', 'draft_ready', 'ai_processing', 'admin_review')
        ORDER BY q.quote_created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_quotes = [];
    
    foreach ($quotes as $quote) {
        // Simple distance calculation without AI
        $distance_km = getSimpleDistance($quote['address'] ?? '');
        
        // Parse services
        $services = [];
        if (!empty($quote['selected_services'])) {
            $services = json_decode($quote['selected_services'], true) ?: [];
        }
        
        // Generate basic line items
        $line_items = generateBasicLineItems($services);

        $formatted_quotes[] = [
            'id' => $quote['id'],
            'status' => $quote['quote_status'],
            'customer_name' => $quote['customer_name'],
            'customer_email' => $quote['customer_email'],
            'phone' => $quote['phone'],
            'address' => $quote['address'],
            'distance_km' => $distance_km,
            'vehicle_type' => 'truck',
            'travel_cost' => $distance_km * 1.00,
            'files' => [], // Skip files for now
            'ai_summary' => 'Manual review required',
            'line_items' => $line_items,
            'subtotal' => 0,
            'discount_name' => '',
            'discount_amount' => 0,
            'discount_type' => 'dollar',
            'discount_value' => 0,
            'final_total' => 0
        ];
    }

    echo json_encode([
        'success' => true,
        'quotes' => $formatted_quotes,
        'message' => 'Simplified loading - ' . count($formatted_quotes) . ' quotes'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getSimpleDistance($address) {
    $address_lower = strtolower($address);
    
    if (strpos($address_lower, 'nelson') !== false) return 10;
    if (strpos($address_lower, 'castlegar') !== false) return 25;
    if (strpos($address_lower, 'trail') !== false) return 45;
    if (strpos($address_lower, 'rossland') !== false) return 35;
    if (strpos($address_lower, 'salmo') !== false) return 55;
    if (strpos($address_lower, 'kaslo') !== false) return 75;
    if (strpos($address_lower, 'cranbrook') !== false) return 180;
    if (strpos($address_lower, 'vancouver') !== false) return 650;
    
    return 40; // Default
}

function generateBasicLineItems($services) {
    $items = [];
    $pricing = [
        'removal' => ['name' => 'Tree Removal', 'price' => 800],
        'pruning' => ['name' => 'Tree Pruning', 'price' => 400],
        'assessment' => ['name' => 'Tree Assessment', 'price' => 150],
        'cabling' => ['name' => 'Tree Cabling', 'price' => 600],
        'planting' => ['name' => 'Tree Planting', 'price' => 200]
    ];
    
    foreach ($services as $service) {
        if (isset($pricing[$service])) {
            $items[] = [
                'service_name' => $pricing[$service]['name'],
                'description' => 'Professional ' . $service . ' service',
                'price' => $pricing[$service]['price'],
                'included' => true,
                'prescription' => ''
            ];
        }
    }
    
    return $items;
}
?> 