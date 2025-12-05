<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    // Get date filters if provided
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // Build query
    $sql = "
        SELECT 
            q.id,
            q.customer_id,
            q.selected_services,
            q.notes,
            q.quote_status,
            q.estimated_cost,
            q.created_at,
            c.name as customer_name,
            c.email,
            c.phone,
            c.address,
            c.geo_latitude,
            c.geo_longitude,
            c.geo_accuracy
        FROM quotes q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE DATE(q.created_at) BETWEEN ? AND ?
        ORDER BY q.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process quotes
    $processed_quotes = [];
    foreach ($quotes as $quote) {
        // Parse services
        $services = [];
        if (!empty($quote['selected_services'])) {
            $services = json_decode($quote['selected_services'], true) ?: [];
        }
        
        // Check if this is a field capture
        $is_field_capture = false;
        if (strpos($quote['notes'], '[Field Data]') !== false) {
            $is_field_capture = true;
            
            // Extract field data if present
            preg_match('/\[Field Data\]: (.+)$/m', $quote['notes'], $matches);
            if (!empty($matches[1])) {
                $field_data = json_decode($matches[1], true);
                if ($field_data) {
                    // Use GPS from field data if not in customer record
                    if (empty($quote['geo_latitude']) && !empty($field_data['gps'])) {
                        $quote['geo_latitude'] = $field_data['gps']['lat'];
                        $quote['geo_longitude'] = $field_data['gps']['lng'];
                        $quote['geo_accuracy'] = $field_data['gps']['accuracy'] ?? null;
                    }
                    
                    // Use estimated cost from field data
                    if (empty($quote['estimated_cost']) && !empty($field_data['estimatedCost'])) {
                        $quote['estimated_cost'] = $field_data['estimatedCost'];
                    }
                }
            }
        }
        
        // Determine status
        $status = $quote['quote_status'] ?? 'pending';
        if (in_array('emergency', $services)) {
            $status = 'emergency';
        }
        
        $processed_quotes[] = [
            'id' => $quote['id'],
            'customer_id' => $quote['customer_id'],
            'customer_name' => $quote['customer_name'],
            'email' => $quote['email'],
            'phone' => $quote['phone'],
            'address' => $quote['address'],
            'lat' => $quote['geo_latitude'] ? floatval($quote['geo_latitude']) : null,
            'lng' => $quote['geo_longitude'] ? floatval($quote['geo_longitude']) : null,
            'accuracy' => $quote['geo_accuracy'] ? floatval($quote['geo_accuracy']) : null,
            'services' => $services,
            'status' => $status,
            'estimated_cost' => $quote['estimated_cost'] ? floatval($quote['estimated_cost']) : null,
            'created_at' => $quote['created_at'],
            'fieldCapture' => $is_field_capture
        ];
    }
    
    // Filter to only quotes with location data if requested
    if (isset($_GET['with_location']) && $_GET['with_location'] === 'true') {
        $processed_quotes = array_filter($processed_quotes, function($q) {
            return $q['lat'] !== null && $q['lng'] !== null;
        });
    }
    
    echo json_encode([
        'success' => true,
        'quotes' => array_values($processed_quotes),
        'total' => count($processed_quotes),
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching quotes with location: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch quotes',
        'message' => $e->getMessage()
    ]);
}











