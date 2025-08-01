<?php
// OpenAI o4-mini-2025-04-16 Analysis - Fast and cost-effective
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $quote_id = $_POST['quote_id'] ?? $_GET['quote_id'] ?? null;
    if (!$quote_id) {
        throw new Exception("Quote ID required");
    }

    require_once __DIR__ . '/../config/database-simple.php';
    require_once __DIR__ . '/../config/config.php';

    // Get quote and customer information
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name, c.phone 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        throw new Exception("Quote not found");
    }

    // Get uploaded media files
    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    if (empty($media_files)) {
        throw new Exception("No media files found for analysis");
    }

    // Prepare media content for o1-mini (simplified approach)
    $media_content = [];
    $media_summary = [];
    
    foreach ($media_files as $media) {
        $file_path = $media['file_path'];
        $file_type = $media['file_type'];
        
        if (!file_exists($file_path)) {
            error_log("Media file not found: $file_path");
            continue;
        }
        
        $media_summary[] = [
            'filename' => $media['filename'],
            'type' => $file_type,
            'size' => formatFileSize($media['file_size'] ?? 0)
        ];
        
        if ($file_type === 'image') {
            $imageData = base64_encode(file_get_contents($file_path));
            $media_content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $media['mime_type'] . ';base64,' . $imageData,
                    'detail' => 'low' // o1-mini works better with low detail
                ]
            ];
        } elseif ($file_type === 'video') {
            // For o1-mini, just describe the video file
            $size = round($media['file_size'] / (1024*1024), 1);
            $media_content[] = [
                'type' => 'text',
                'text' => "Video file: {$media['filename']} ({$size}MB). Note: Video analysis not supported in o1-mini mode."
            ];
        }
    }

    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    // o1-mini optimized prompt (simpler, more direct)
    $prompt = "Analyze tree service request for Carpe Tree'em (BC arborist service).

SERVICES REQUESTED: $services_text
CUSTOMER NOTES: " . ($quote['notes'] ?? 'None') . "

Provide concise analysis:
1. Tree species & condition
2. Safety risks
3. Service recommendations  
4. Cost estimate range
5. Priority level

Keep response under 500 words for efficiency.";

    if (!empty($OPENAI_API_KEY) && !empty($media_content)) {
        $messages = [
            [
                'role' => 'user',
                'content' => array_merge([['type' => 'text', 'text' => $prompt]], $media_content)
            ]
        ];

        $openai_request = [
            'model' => 'o4-mini-2025-04-16',
            'messages' => $messages,
            'max_completion_tokens' => 1000 // Lower token limit for speed
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($openai_request),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $OPENAI_API_KEY
            ],
            CURLOPT_TIMEOUT => 30 // Shorter timeout for o1-mini
        ]);

        $start_time = microtime(true);
        $response = curl_exec($curl);
        $processing_time = (microtime(true) - $start_time) * 1000; // in milliseconds

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code !== 200) {
            throw new Exception("OpenAI o1-mini API error: HTTP $http_code");
        }

        $ai_result = json_decode($response, true);
        
        if (isset($ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            $function_args = json_decode($ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);
            $ai_analysis = json_encode($function_args, JSON_PRETTY_PRINT);
        } elseif (isset($ai_result['choices'][0]['message']['content'])) {
            $ai_analysis = $ai_result['choices'][0]['message']['content'];
        } else {
            throw new Exception("Invalid o4-mini response format");
        }
        
        // Calculate cost (o1-mini pricing)
        $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
        $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
        $cost = ($input_tokens * 0.000003) + ($output_tokens * 0.000012); // o1-mini rates
        
    } else {
        $ai_analysis = "⚠️ o4-mini-2025-04-16 analysis unavailable. API key: " . (empty($OPENAI_API_KEY) ? "missing" : "set") . ", Media: " . count($media_content) . " items";
        $cost = 0;
    }

    // Format the analysis
    $analysis_summary = "🤖 OpenAI o4-mini-2025-04-16 Analysis (Fast & Efficient)\n\n";
    $analysis_summary .= "📁 Media: " . implode(', ', array_column($media_summary, 'filename')) . "\n";
    $analysis_summary .= "💰 Cost: $" . number_format($cost, 4) . "\n\n";
    $analysis_summary .= "🔍 Analysis:\n" . $ai_analysis;

    // Store results in database with model identifier
    $analysis_data = [
        'model' => 'o4-mini-2025-04-16',
        'analysis' => $ai_analysis,
        'cost' => $cost,
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'tokens_used' => ($input_tokens ?? 0) + ($output_tokens ?? 0)
    ];

    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_o4_mini_analysis = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([json_encode($analysis_data), $quote_id]);

    // Track cost and performance
    require_once __DIR__ . '/../utils/cost-tracker.php';
    $cost_tracker = new CostTracker($pdo);
    
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'gpt-4o-mini',
        'provider' => 'openai',
        'input_tokens' => $input_tokens ?? 0,
        'output_tokens' => $output_tokens ?? 0,
        'processing_time_ms' => $processing_time,
        'reasoning_effort' => 'low', // o4-mini is fast model
        'media_files_processed' => count($media_files),
        'transcriptions_generated' => 0, // Count actual transcriptions if available
        'tools_used' => ['function_calling', 'vision', 'multimodal'],
        'analysis_quality_score' => 0.85 // Good quality for cost-effective model
    ]);

    echo json_encode([
        'success' => true,
        'model' => 'gpt-4o-mini',
        'quote_id' => $quote_id,
        'analysis' => $analysis_summary,
        'cost' => $cost,
        'cost_tracking' => $cost_data,
        'input_tokens' => $input_tokens ?? 0,
        'output_tokens' => $output_tokens ?? 0,
        'processing_time_ms' => $processing_time,
        'media_count' => count($media_files)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'o1-mini',
        'error' => $e->getMessage(),
        'quote_id' => $quote_id ?? null
    ]);
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . 'MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }
    return $bytes . 'B';
}
?>