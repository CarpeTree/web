<?php
// START NEW - AI Quote Processing
// This script is called asynchronously after quote submission

if (php_sapi_name() === 'cli' && isset($argv[1])) {
    // Called from command line with quote ID
    $quote_id = (int)$argv[1];
} elseif (isset($_GET['quote_id'])) {
    // Called via HTTP
    $quote_id = (int)$_GET['quote_id'];
} else {
    die("Quote ID required\n");
}

require_once '../config/database-simple.php';
require_once '../config/config.php';
require_once '../utils/mailer.php';

try {
    // Get quote and customer information
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name, c.phone 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ? AND q.quote_status = 'ai_processing'
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        throw new Exception("Quote not found or not ready for processing");
    }

    // Get uploaded media files
    $stmt = $pdo->prepare("
        SELECT * FROM media 
        WHERE quote_id = ? AND processed_by_ai = 0
        ORDER BY uploaded_at ASC
    ");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    if (empty($media_files)) {
        throw new Exception("No media files found for processing");
    }

    // Load AI system prompt and schema
    $system_prompt = file_get_contents('../../ai/system_prompt.txt');
    $schema = json_decode(file_get_contents('../../ai/schema.json'), true);

    if (!$system_prompt || !$schema) {
        throw new Exception("AI configuration files not found");
    }

    // Prepare OpenAI API request
    $api_key = $OPENAI_API_KEY ?? '';
    if (empty($api_key)) {
        throw new Exception("OpenAI API key not configured");
    }

    // Build image URLs for OpenAI
    $image_urls = [];
    foreach ($media_files as $media) {
        if ($media['file_type'] === 'image') {
            // Convert to base64 for API
            $image_data = base64_encode(file_get_contents($media['file_path']));
            $mime_type = $media['mime_type'];
            $image_urls[] = "data:$mime_type;base64,$image_data";
        }
    }

    if (empty($image_urls)) {
        throw new Exception("No images found for AI analysis");
    }

    // Prepare messages for OpenAI
    $messages = [
        [
            'role' => 'system',
            'content' => $system_prompt
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Please analyze these tree photos and provide a comprehensive quote. Customer selected these services: " . $quote['selected_services'] . ". Additional notes: " . ($quote['notes'] ?? 'None provided.')
                ]
            ]
        ]
    ];

    // Add images to the user message
    foreach ($image_urls as $image_url) {
        $messages[1]['content'][] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => $image_url
            ]
        ];
    }

    // Make OpenAI API request
    $openai_request = [
                    'model' => 'o3', // OpenAI o3 - most advanced reasoning model with vision capabilities
        'messages' => $messages,
        'temperature' => 0.2,
        'max_tokens' => 4000,
        'tools' => [$schema],
        'tool_choice' => ['type' => 'function', 'function' => ['name' => 'draft_tree_quote']]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($openai_request),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ],
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("OpenAI API request failed: $response");
    }

    $ai_response = json_decode($response, true);
    
    if (!isset($ai_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
        throw new Exception("Invalid AI response format");
    }

    $quote_data = json_decode($ai_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);

    // Start transaction for database updates
    $pdo->beginTransaction();

    // Update quote with AI analysis
    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET quote_status = 'draft_ready', 
            total_estimate = ?, 
            ai_analysis_complete = 1,
            ai_response_json = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $quote_data['quote_summary']['estimated_total_cost'],
        json_encode($quote_data),
        $quote_id
    ]);

    // Insert tree records
    foreach ($quote_data['trees'] as $tree_data) {
        $stmt = $pdo->prepare("
            INSERT INTO trees (
                quote_id, tree_species, tree_height_meters, tree_height_feet,
                tree_dbh_cm, tree_dbh_inches, tree_condition, tree_lean_desc,
                proximity_to_structures, proximity_to_powerlines, is_conifer,
                within_20m_building, sprinkler_upsell_applicable, ai_confidence_score, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $quote_id,
            $tree_data['species']['common_name'] ?? 'Unknown',
            $tree_data['measurements']['height_meters'] ?? null,
            $tree_data['measurements']['height_feet'] ?? null,
            $tree_data['measurements']['dbh_cm'] ?? null,
            $tree_data['measurements']['dbh_inches'] ?? null,
            $tree_data['condition']['overall_health'] ?? 'fair',
            $tree_data['condition']['lean_description'] ?? null,
            $tree_data['location_context']['distance_to_structures_meters'] < 20 ? 1 : 0,
            $tree_data['location_context']['power_line_proximity'] ?? 0,
            $tree_data['species']['is_conifer'] ?? 0,
            $tree_data['location_context']['within_20m_building'] ?? 0,
            ($tree_data['species']['is_conifer'] && $tree_data['location_context']['within_20m_building']) ? 1 : 0,
            $tree_data['species']['confidence_score'] ?? 0.5,
            json_encode($tree_data)
        ]);

        $tree_id = $pdo->lastInsertId();

        // Insert work orders for this tree
        foreach ($tree_data['services'] as $service) {
            $stmt = $pdo->prepare("
                INSERT INTO tree_work_orders (
                    tree_id, quote_id, service_type, service_description,
                    estimated_hours, hourly_rate, material_cost, equipment_cost,
                    cleanup_cost, total_cost, cut_count, removal_method,
                    disposal_method, ansi_standards_applied, refuses_topping
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $cost = $service['cost_breakdown'];
            $technical = $service['technical_details'] ?? [];

            $stmt->execute([
                $tree_id,
                $quote_id,
                $service['service_type'],
                $service['service_description'],
                $cost['estimated_hours'] ?? 0,
                $cost['hourly_rate'] ?? 150,
                $cost['material_cost'] ?? 0,
                $cost['equipment_cost'] ?? 0,
                $cost['cleanup_cost'] ?? 0,
                $cost['total_cost'],
                $technical['cut_count'] ?? 0,
                $technical['removal_method'] ?? null,
                $technical['disposal_method'] ?? null,
                $technical['ansi_standards'] ?? 1,
                $technical['topping_refused'] ?? 1
            ]);
        }
    }

    // Mark media files as processed
    $stmt = $pdo->prepare("UPDATE media SET processed_by_ai = 1 WHERE quote_id = ?");
    $stmt->execute([$quote_id]);

    // Commit transaction
    $pdo->commit();

    // Send admin notification email
    sendAdminQuoteAlert($quote, $quote_data);

    echo "Quote $quote_id processed successfully\n";

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Update quote status to indicate error
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'submitted' WHERE id = ?");
    $stmt->execute([$quote_id]);

    error_log("AI Quote Processing Error for Quote $quote_id: " . $e->getMessage());
    echo "Error processing quote $quote_id: " . $e->getMessage() . "\n";
}

function sendAdminQuoteAlert($quote, $quote_data) {
    $subject = "New Tree Quote Ready for Review - Quote #{$quote['id']}";
    $template_data = [
        'quote_id' => $quote['id'],
        'customer_name' => $quote['name'] ?: 'Not provided',
        'customer_email' => $quote['email'],
        'customer_phone' => $quote['phone'] ?: 'Not provided',
        'total_estimate' => number_format($quote_data['quote_summary']['estimated_total_cost'], 2),
        'tree_count' => $quote_data['quote_summary']['total_trees'],
        'urgent_items' => $quote_data['quote_summary']['priority_breakdown']['urgent'] ?? 0,
        'admin_url' => $_ENV['ADMIN_URL'] ?? 'https://your-domain.com/admin'
    ];

    sendEmail(
        $_ENV['ADMIN_EMAIL'] ?? 'admin@carpetree.com',
        $subject,
        'quote_alert_admin',
        $template_data
    );
}
// END NEW
?> 