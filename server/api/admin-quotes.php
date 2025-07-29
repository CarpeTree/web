<?php
header('Content-Type: application/json');
require_once '../config/database-simple.php';

try {
    // Get all quotes that need admin review
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            c.name as customer_name,
            c.email as customer_email,
            c.phone,
            c.address
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status IN ('submitted', 'draft_ready', 'ai_processing', 'admin_review')
        ORDER BY q.quote_created_at DESC
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_quotes = [];
    
    foreach ($quotes as $quote) {
        // Get uploaded files for this quote (using correct media table)
        $file_stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ?");
        $file_stmt->execute([$quote['id']]);
        $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format files for frontend (using correct media table columns)
        $formatted_files = array_map(function($file) {
            return [
                'id' => $file['id'],
                'filename' => $file['filename'],
                'type' => $file['mime_type'],
                'download_url' => "/uploads/{$file['quote_id']}/{$file['filename']}"
            ];
        }, $files);

        // Parse AI response
        $ai_response = json_decode($quote['ai_response_json'], true);
        $services = json_decode($quote['selected_services'], true) ?: [];

        // Calculate distance from Nelson, BC
        $distance_km = calculateDistance($quote['address']);
        
        // Generate line items based on services and AI analysis
        $line_items = generateLineItems($services, $ai_response, count($files) > 0);

        // Format AI summary
        $ai_summary = formatAISummary($ai_response, count($files) > 0);

        $formatted_quotes[] = [
            'id' => $quote['id'],
            'status' => $quote['quote_status'],
            'customer_name' => $quote['customer_name'],
            'customer_email' => $quote['customer_email'],
            'phone' => $quote['phone'],
            'address' => $quote['address'],
            'distance_km' => $distance_km,
            'vehicle_type' => 'truck', // Default
            'travel_cost' => $distance_km * 1.00, // Default truck rate
            'files' => $formatted_files,
            'ai_summary' => $ai_summary,
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
        'quotes' => $formatted_quotes
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function calculateDistance($customer_address) {
    // Base location: 4530 Blewitt Rd., Nelson BC
    $base_lat = 49.4928;
    $base_lng = -117.2948;
    
    // Try to geocode customer address (simplified - you can enhance this)
    // For now, return approximate distances based on common areas
    $address_lower = strtolower($customer_address);
    
    if (strpos($address_lower, 'nelson') !== false) {
        return 10; // Fixed distance for Nelson area
    } elseif (strpos($address_lower, 'moyie') !== false) {
        return 200; // Moyie Lake is ~200km from Nelson (near Cranbrook)
    } elseif (strpos($address_lower, 'slocan') !== false) {
        return 55; // Slocan Valley/Pools area - about 55km from Nelson
    } elseif (strpos($address_lower, 'castlegar') !== false) {
        return 25;
    } elseif (strpos($address_lower, 'cranbrook') !== false) {
        return 180; // Cranbrook area
    } elseif (strpos($address_lower, 'trail') !== false) {
        return 45;
    } elseif (strpos($address_lower, 'rossland') !== false) {
        return 35;
    } elseif (strpos($address_lower, 'salmo') !== false) {
        return 55;
    } elseif (strpos($address_lower, 'kaslo') !== false) {
        return 75;
    } elseif (strpos($address_lower, 'new denver') !== false) {
        return 85;
    } elseif (strpos($address_lower, 'nakusp') !== false) {
        return 120;
    } elseif (strpos($address_lower, 'vancouver') !== false) {
        return 650;
    } elseif (strpos($address_lower, 'calgary') !== false) {
        return 420;
    } else {
        // Default for unknown addresses - consistent 40km estimate
        return 40;
    }
}

function generateLineItems($services, $ai_response, $has_media) {
    $line_items = [];
    
    // Base pricing structure
    $service_pricing = [
        'removal' => [
            'name' => 'Tree Removal',
            'base_price' => 800,
            'min_price' => 400,
            'max_price' => 2500,
            'description' => 'Complete tree removal including cleanup'
        ],
        'pruning' => [
            'name' => 'Tree Pruning',
            'base_price' => 400,
            'min_price' => 200,
            'max_price' => 1200,
            'description' => 'Professional pruning following Ed Gilman standards'
        ],
        'assessment' => [
            'name' => 'Tree Assessment',
            'base_price' => 150,
            'min_price' => 100,
            'max_price' => 300,
            'description' => 'Professional arborist assessment and report'
        ],
        'cabling' => [
            'name' => 'Tree Cabling/Support',
            'base_price' => 600,
            'min_price' => 400,
            'max_price' => 1500,
            'description' => 'Structural support system installation'
        ],
        'planting' => [
            'name' => 'Tree Planting',
            'base_price' => 200,
            'min_price' => 100,
            'max_price' => 500,
            'description' => 'Tree selection, planting, and initial care'
        ],
        'wildfire_risk' => [
            'name' => 'Wildfire Risk Reduction',
            'base_price' => 500,
            'min_price' => 300,
            'max_price' => 1500,
            'description' => 'Fuel modification and defensible space creation'
        ],
        'sprinkler_system' => [
            'name' => 'Sprinkler System',
            'base_price' => 2500,
            'min_price' => 1500,
            'max_price' => 5000,
            'description' => 'Fire suppression sprinkler installation'
        ],
        'emergency' => [
            'name' => 'Emergency Tree Service',
            'base_price' => 1200,
            'min_price' => 800,
            'max_price' => 3000,
            'description' => 'Emergency response for hazardous trees'
        ]
    ];

    foreach ($services as $service) {
        if (isset($service_pricing[$service])) {
            $pricing = $service_pricing[$service];
            
            $item = [
                'service_name' => $pricing['name'],
                'description' => $pricing['description'],
                'price' => $pricing['base_price'],
                'min_price' => $pricing['min_price'],
                'max_price' => $pricing['max_price'],
                'suggested_price' => $pricing['base_price'],
                'included' => true,
                'prescription' => ''
            ];

            // Add Ed Gilman style prescriptions for pruning
            if ($service === 'pruning') {
                $item['prescription'] = generatePruningPrescription($has_media);
            }

            // Adjust pricing based on AI analysis if available
            if ($ai_response && isset($ai_response['cost_breakdown'])) {
                foreach ($ai_response['cost_breakdown'] as $cost_item) {
                    if ($cost_item['service'] === $service) {
                        $item['price'] = $cost_item['estimated_cost'];
                        $item['suggested_price'] = $cost_item['estimated_cost'];
                        break;
                    }
                }
            }

            $line_items[] = $item;
        }
    }

    // Add suggested optional services
    $optional_services = generateOptionalServices($services, $has_media);
    foreach ($optional_services as $optional) {
        $optional['included'] = false; // Optional services start unchecked
        $line_items[] = $optional;
    }

    return $line_items;
}

function generatePruningPrescription($has_media) {
    if ($has_media) {
        // With media, provide specific prescription
        return "Ed Gilman Standards: 4 removal cuts at 2\", 6 reduction cuts at 7\", crown cleaning of deadwood <1\"";
    } else {
        // Without media, general prescription
        return "Ed Gilman Standards: Structural pruning assessment required for specific prescription";
    }
}

function generateOptionalServices($selected_services, $has_media) {
    $optional = [];
    
    // Suggest planting with every removal
    if (in_array('removal', $selected_services) && !in_array('planting', $selected_services)) {
        $optional[] = [
            'service_name' => 'Replacement Tree Planting',
            'description' => 'Optional: Plant a new tree to replace the removed one',
            'price' => 250,
            'min_price' => 150,
            'max_price' => 400,
            'suggested_price' => 250,
            'included' => false,
            'prescription' => ''
        ];
    }

    // Suggest sprinkler with conifer pruning near structures
    if (in_array('pruning', $selected_services)) {
        $optional[] = [
            'service_name' => 'Fire Suppression Sprinkler',
            'description' => 'Optional: Recommended for conifers near structures',
            'price' => 1200,
            'min_price' => 800,
            'max_price' => 2000,
            'suggested_price' => 1200,
            'included' => false,
            'prescription' => ''
        ];
    }

    // Suggest assessment if not already selected
    if (!in_array('assessment', $selected_services)) {
        $optional[] = [
            'service_name' => 'Professional Assessment',
            'description' => 'Optional: Detailed written assessment and recommendations',
            'price' => 150,
            'min_price' => 100,
            'max_price' => 250,
            'suggested_price' => 150,
            'included' => false,
            'prescription' => ''
        ];
    }

    return $optional;
}

function formatAISummary($ai_response, $has_media) {
    if (!$ai_response) {
        return "No AI analysis available. Manual assessment required.";
    }

    $summary = "";
    
    if ($has_media) {
        $summary .= "🎬 Video Analysis Complete\n\n";
    } else {
        $summary .= "📝 Text-based Analysis\n\n";
    }

    if (isset($ai_response['quote_summary'])) {
        $qs = $ai_response['quote_summary'];
        
        if (isset($qs['total_trees']) && $qs['total_trees'] > 0) {
            $summary .= "Trees identified: {$qs['total_trees']}\n";
        }
        
        if (isset($qs['analysis_method'])) {
            $summary .= "Analysis method: {$qs['analysis_method']}\n";
        }
    }

    if (isset($ai_response['recommendations']) && is_array($ai_response['recommendations'])) {
        $summary .= "\nKey Recommendations:\n";
        foreach ($ai_response['recommendations'] as $rec) {
            if (isset($rec['description'])) {
                $summary .= "• {$rec['description']}\n";
            }
        }
    }

    if (isset($ai_response['notes'])) {
        $summary .= "\nNotes: {$ai_response['notes']}";
    }

    return $summary;
}
?> 