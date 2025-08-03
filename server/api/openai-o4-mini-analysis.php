<?php
// OpenAI o4-mini Analysis Script

// Custom shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && (error_reporting() & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'model' => 'o4-mini',
            'error' => 'A fatal error occurred: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'quote_id' => $_REQUEST['quote_id'] ?? null
        ]);
        exit;
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
ignore_user_abort(true);
set_time_limit(600);

try {
    // 1. SETUP & CONFIG
    $quote_id = $_POST['quote_id'] ?? $_GET['quote_id'] ?? null;
    if (!$quote_id && isset($argv[1])) {
        $quote_id = $argv[1];
    }
    if (!$quote_id) {
        throw new Exception("Quote ID is required.");
    }

    // If running via HTTP, send immediate 200 and continue in background
if (php_sapi_name() !== 'cli') {
    header('X-Accel-Buffering: no');
    echo json_encode(['success' => true, 'queued' => true, 'model' => 'o4-mini', 'quote_id' => $quote_id]);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

require_once __DIR__ . '/../config/config.php';
    
    // Get database connection
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed. Please try again later.");
    }
    require_once __DIR__ . '/../utils/media-preprocessor.php';
    require_once __DIR__ . '/../utils/cost-tracker.php';

    // 2. FETCH DATA
    $stmt = $pdo->prepare("SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone FROM quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch();

    if (!$quote_data) {
        throw new Exception("Quote #{$quote_id} not found.");
    }

    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    if (empty($media_files)) {
        throw new Exception("No media files found for analysis.");
    }

    // 3. PREPROCESS CONTEXT
    $preprocessor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $aggregated_context = $preprocessor->preprocessAllMedia();
    
    $context_text = $aggregated_context['context_text'];
    $visual_content = $aggregated_context['visual_content'];

    // 4. LOAD AI PROMPTS & SCHEMA
    $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
    $json_schema_string = file_get_contents(__DIR__ . '/../../ai/schema.json');
    $json_schema = json_decode($json_schema_string, true);

    if (!$system_prompt || !$json_schema) {
        throw new Exception("Failed to load AI prompt or schema.");
    }

    // 5. PREPARE OPENAI API REQUEST
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => array_merge([['type' => 'text', 'text' => $context_text]], $visual_content)]
    ];

    $openai_request = [
        'model' => 'o4-mini',
        'messages' => $messages,
        'tools' => [$json_schema],
        'tool_choice' => ['type' => 'function', 'function' => ['name' => 'draft_tree_quote']],
        'max_completion_tokens' => 100000,
        
    ];

    // Validate API key
    if (empty($OPENAI_API_KEY)) {
        throw new Exception("OpenAI API key not configured or empty");
    }
    
    // Log API request for debugging
    error_log("OpenAI API request for quote #{$quote_id}: " . json_encode([
        "model" => $openai_request["model"],
        "message_count" => count($openai_request["messages"]),
        "has_tools" => !empty($openai_request["tools"])
    ]));

    // 6. EXECUTE API CALL
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
        CURLOPT_TIMEOUT => 120
    ]);

    $start_time = microtime(true);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    $processing_time = (microtime(true) - $start_time) * 1000;

    if ($http_code !== 200) {
        throw new Exception("OpenAI o4-mini API error. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
    }

    // 7. PARSE RESPONSE & CALCULATE COST
    $ai_result = json_decode($response, true);
    $ai_analysis_json = $ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? null;

    if (!$ai_analysis_json) {
        throw new Exception("Invalid o4-mini response format or missing tool call. Full response: " . $response);
    }
    
    $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
    $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
    
    // 8. STORE RESULTS & TRACK COST
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'o4-mini',
        'provider' => 'openai',
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
    ]);

    
        // Validate and clean up JSON response before storing
        if (empty($response)) {
            throw new Exception("Empty response from OpenAI API. Check API key and request.");
        }
        
        $parsed_analysis = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from AI: " . json_last_error_msg());
        }
        
        // Fix array formatting issues in recommendations
        if (isset($parsed_analysis["analysis"]["recommendations"]) && is_array($parsed_analysis["analysis"]["recommendations"])) {
            $fixed_recommendations = [];
            foreach ($parsed_analysis["analysis"]["recommendations"] as $rec) {
                if (is_string($rec) && trim($rec) !== "") {
                    $fixed_recommendations[] = trim($rec);
                } elseif (is_array($rec)) {
                    // Convert array to string representation
                    $fixed_recommendations[] = implode(", ", array_filter($rec, "is_string"));
                }
            }
            $parsed_analysis["analysis"]["recommendations"] = array_filter($fixed_recommendations);
        }
        
        $ai_response = json_encode($parsed_analysis);

$analysis_data_to_store = [
        'model' => 'o4-mini',
        'analysis' => json_decode($ai_analysis_json, true),
        'cost' => $cost_data['total_cost'],
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
        'media_summary' => $aggregated_context['media_summary']
    ];

    // Save with connection recovery for long processing
    try {
        $stmt = $pdo->prepare("UPDATE quotes SET ai_o4_mini_analysis = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'server has gone away') !== false) {
            // Reconnect and retry
            $pdo = getDatabaseConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE quotes SET ai_o4_mini_analysis = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);
            } else {
                throw new Exception("Database reconnection failed: " . $e->getMessage());
            }
        } else {
            throw $e;
        }
    }

    // 9. SEND SUCCESS RESPONSE
    echo json_encode([
        'success' => true,
        'model' => 'o4-mini',
        'quote_id' => $quote_id,
        'analysis' => $analysis_data_to_store,
        'cost_tracking' => $cost_data
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'o4-mini',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null,
        'trace' => $e->getTraceAsString()
    ]);
}
?>
