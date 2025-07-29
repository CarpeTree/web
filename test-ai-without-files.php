<?php
// Test AI processing without files using just text information
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== AI PROCESSING WITHOUT FILES TEST ===\n\n";

try {
    // Get quotes that need processing
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.quote_status = 'ai_processing' AND q.ai_analysis_complete = 0
        LIMIT 1
    ");
    $stmt->execute();
    $quote = $stmt->fetch();
    
    if (!$quote) {
        echo "❌ No quotes found for processing\n";
        exit;
    }
    
    echo "Processing Quote ID: {$quote['id']}\n";
    echo "Customer: {$quote['name']} ({$quote['email']})\n";
    echo "Services: {$quote['selected_services']}\n";
    echo "Notes: {$quote['notes']}\n\n";
    
    // Simulate AI processing with text-only information
    echo "🤖 Simulating AI analysis without files...\n";
    
    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    // Create a mock AI response based on the available information
    $mock_ai_response = [
        'quote_summary' => [
            'customer_name' => $quote['name'],
            'customer_email' => $quote['email'],
            'services_requested' => $services,
            'estimated_total_cost' => 0,
            'total_trees' => 0,
            'analysis_method' => 'text_only_no_images',
            'requires_in_person_assessment' => true
        ],
        'recommendations' => [],
        'cost_breakdown' => [],
        'notes' => 'No images provided. In-person assessment required for accurate quote.',
        'processing_timestamp' => date('c'),
        'ai_model_used' => 'text_analysis_fallback'
    ];
    
    // Estimate costs based on service types
    $base_costs = [
        'removal' => 800,
        'pruning' => 400,
        'assessment' => 150,
        'cabling' => 600,
        'stump_grinding' => 300,
        'emergency' => 1200,
        'planting' => 200,
        'wildfire_risk' => 500,
        'sprinkler_system' => 2500
    ];
    
    $total_estimate = 0;
    foreach ($services as $service) {
        if (isset($base_costs[$service])) {
            $cost = $base_costs[$service];
            $total_estimate += $cost;
            $mock_ai_response['cost_breakdown'][] = [
                'service' => $service,
                'estimated_cost' => $cost,
                'notes' => 'Preliminary estimate - requires in-person assessment'
            ];
        }
    }
    
    $mock_ai_response['quote_summary']['estimated_total_cost'] = $total_estimate;
    
    if (!empty($quote['notes'])) {
        $mock_ai_response['recommendations'][] = [
            'type' => 'customer_notes',
            'description' => 'Customer notes: ' . $quote['notes'],
            'priority' => 'medium'
        ];
    }
    
    $mock_ai_response['recommendations'][] = [
        'type' => 'assessment_required',
        'description' => 'In-person assessment recommended for accurate quote and safety evaluation',
        'priority' => 'high'
    ];
    
    echo "✅ Mock AI response generated:\n";
    echo json_encode($mock_ai_response, JSON_PRETTY_PRINT) . "\n\n";
    
    // Update the quote with mock AI response
    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_analysis_complete = 1, 
            ai_response_json = ?,
            total_estimate = ?,
            quote_status = 'draft_ready'
        WHERE id = ?
    ");
    
    $success = $stmt->execute([
        json_encode($mock_ai_response),
        $total_estimate,
        $quote['id']
    ]);
    
    if ($success) {
        echo "✅ Quote updated successfully\n";
        echo "Status: draft_ready\n";
        echo "Estimated total: $" . number_format($total_estimate, 2) . "\n";
        
        // Test sending email notification
        echo "\n📧 Testing email notification...\n";
        
        if (file_exists(__DIR__ . '/server/utils/mailer.php')) {
            require_once __DIR__ . '/server/utils/mailer.php';
            
            $email_data = [
                'customer_name' => $quote['name'],
                'quote_id' => $quote['id'],
                'estimated_total' => number_format($total_estimate, 2),
                'services' => $services_text,
                'requires_assessment' => true
            ];
            
            echo "Email data prepared: " . json_encode($email_data) . "\n";
            echo "📧 Email functionality available - ready to send\n";
        } else {
            echo "❌ Email functionality not available\n";
        }
        
    } else {
        echo "❌ Failed to update quote\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 