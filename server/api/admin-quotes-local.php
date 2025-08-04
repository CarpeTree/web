<?php
// Local development admin quotes API - no database required
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Mock quote data for local development
$mockQuotes = [
    [
        'id' => 1,
        'customer_name' => 'Phil Bajenski',
        'customer_email' => 'phil.bajenski@gmail.com',
        'customer_phone' => '778-655-3741',
        'address' => '123 Tree Lane, Vancouver, BC V6B 1A1',
        'quote_status' => 'submitted',
        'selected_services' => 'Tree Assessment, Pruning',
        'quote_created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'quote_expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'total_estimate' => 750.00,
        'notes' => 'Large maple tree assessment - concerned about dead branches near house.',
        'gps_lat' => 49.2827,
        'gps_lng' => -123.1207,
        'media_count' => 3,
        'ai_gemini_analysis' => 'Tree appears healthy overall with minor pruning needs.',
        'ai_o4_mini_analysis' => 'Assessment indicates routine maintenance required.',
        'uploaded_files' => [
            ['filename' => 'tree_photo_1.jpg', 'mime_type' => 'image/jpeg'],
            ['filename' => 'tree_photo_2.jpg', 'mime_type' => 'image/jpeg'],
            ['filename' => 'property_overview.mp4', 'mime_type' => 'video/mp4']
        ]
    ],
    [
        'id' => 2,
        'customer_name' => 'Sarah Johnson',
        'customer_email' => 'sarah.j@email.com',
        'customer_phone' => '604-555-0123',
        'address' => '456 Oak Street, Burnaby, BC V5H 2M4',
        'quote_status' => 'ai_processing',
        'selected_services' => 'Tree Removal',
        'quote_created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'quote_expires_at' => date('Y-m-d H:i:s', strtotime('+29 days')),
        'total_estimate' => 1200.00,
        'notes' => 'Dead pine tree removal required for safety.',
        'gps_lat' => 49.2488,
        'gps_lng' => -122.9805,
        'media_count' => 5,
        'ai_gemini_analysis' => null,
        'ai_o4_mini_analysis' => 'Processing...',
        'uploaded_files' => [
            ['filename' => 'dead_tree.jpg', 'mime_type' => 'image/jpeg'],
            ['filename' => 'property_angle.jpg', 'mime_type' => 'image/jpeg']
        ]
    ],
    [
        'id' => 3,
        'customer_name' => 'Mike Chen',
        'customer_email' => 'mike.chen@gmail.com',
        'customer_phone' => '778-555-9876',
        'address' => '789 Cedar Ave, Richmond, BC V6X 1B2',
        'quote_status' => 'draft_ready',
        'selected_services' => 'Emergency Service, Storm Damage',
        'quote_created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'quote_expires_at' => date('Y-m-d H:i:s', strtotime('+28 days')),
        'total_estimate' => 850.00,
        'notes' => 'Storm damaged branch removal - urgent.',
        'gps_lat' => 49.1666,
        'gps_lng' => -123.1336,
        'media_count' => 2,
        'ai_gemini_analysis' => 'Emergency assessment complete. Branch poses immediate risk.',
        'ai_o4_mini_analysis' => 'Urgent removal recommended within 24 hours.',
        'uploaded_files' => [
            ['filename' => 'storm_damage.jpg', 'mime_type' => 'image/jpeg']
        ]
    ]
];

try {
    // Check if specific quote ID requested
    $quote_id = $_GET['quote_id'] ?? null;
    
    if ($quote_id) {
        // Return specific quote
        $quote = array_filter($mockQuotes, function($q) use ($quote_id) {
            return $q['id'] == $quote_id;
        });
        
        if (empty($quote)) {
            http_response_code(404);
            echo json_encode(['error' => 'Quote not found']);
            exit;
        }
        
        echo json_encode(['quotes' => array_values($quote)]);
    } else {
        // Return all quotes
        echo json_encode([
            'quotes' => $mockQuotes,
            'total_count' => count($mockQuotes),
            'message' => 'Local development mode - mock data'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load quotes: ' . $e->getMessage(),
        'mode' => 'local_development'
    ]);
}
?>