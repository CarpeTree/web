<?php
// OpenAI GPT-5.2 Analysis Script with XHIGH reasoning effort
// Upgraded from GPT-5.1 to GPT-5.2 for maximum thinking capability

// Custom shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && (error_reporting() & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'model' => 'gpt-5.2',
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

// HARD GUARD: prevent public traffic from burning credits
function require_admin_key_or_disable(): void {
    $expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
    if (!$expected) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'AI analysis disabled until ADMIN_API_KEY is configured on the server.'
        ]);
        exit;
    }
    $provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? null;
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}
require_admin_key_or_disable();

// Only allow POST from browser; CLI is allowed for ops
if (php_sapi_name() !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Simple per-session/IP rate limit: 3 requests per 10 minutes
if (php_sapi_name() !== 'cli') {
    session_start();
    $now = time();
    $window = 600; // 10 minutes
    $limit = 3;
    $key = 'rl_gpt_analysis';
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key], function ($ts) use ($now, $window) {
        return ($now - $ts) < $window;
    }));
    if (count($_SESSION[$key]) >= $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please wait before retrying.'
        ]);
        exit;
    }
    $_SESSION[$key][] = $now;
}

try {
    // 1. SETUP & CONFIG
    // Parse JSON body if present
    $raw_input = file_get_contents('php://input');
    $json_body = json_decode($raw_input ?: '[]', true) ?: [];
    
    $quote_id = $json_body['quote_id'] ?? $_POST['quote_id'] ?? $_GET['quote_id'] ?? null;
    if (!$quote_id && isset($argv[1])) {
        $quote_id = $argv[1];
    }
    // Optional additional operator context to steer regeneration
    $extra_context = $json_body['context'] ?? $_POST['context'] ?? $_GET['context'] ?? null;
    if (!$extra_context && isset($argv[2])) { $extra_context = $argv[2]; }
    if (!$quote_id) {
        throw new Exception("Quote ID is required.");
    }

    // If running via HTTP, send immediate 200 and continue in background
    // Unless sync=1 is passed for debugging
    $run_sync = $json_body['sync'] ?? $_GET['sync'] ?? false;
    if (php_sapi_name() !== 'cli' && !$run_sync) {
        header('X-Accel-Buffering: no');
        echo json_encode(['success' => true, 'queued' => true, 'model' => 'gpt-5.2', 'quote_id' => $quote_id]);
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
    
    // 3a. PREFETCH LOGISTICS DATA FROM MAPS API
    // This provides verified location data so the AI doesn't need to guess
    $logistics_context = '';
    try {
        // Build URL for internal API call
        $logistics_url = 'http://localhost/server/api/prefetch-logistics.php?quote_id=' . urlencode($quote_id);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $logistics_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $logistics_response = curl_exec($ch);
        curl_close($ch);
        
        if ($logistics_response) {
            $logistics_data = json_decode($logistics_response, true);
            if (!isset($logistics_data['error']) && isset($logistics_data['ai_context'])) {
                $logistics_context = $logistics_data['ai_context'];
                error_log("Injected logistics context for quote #{$quote_id}");
            }
        }
    } catch (Exception $e) {
        error_log("Failed to fetch logistics data: " . $e->getMessage());
    }
    
    // Build full context with logistics data first (highest priority)
    if (!empty($logistics_context)) {
        $context_text = $logistics_context . "\n\n" . $context_text;
    }
    
    if (!empty($extra_context)) {
        $context_text = "Operator notes (higher priority):\n" . trim($extra_context) . "\n\n" . $context_text;
    }
    $visual_content = $aggregated_context['visual_content'];

    // 4. LOAD AI PROMPTS & SCHEMA
    // Prefer system_prompts.json for GPT-5.2 prompt, fallback to legacy file
    $system_prompt = null;
    $prompts_file = __DIR__ . '/../ai/system_prompts.json';
    if (file_exists($prompts_file)) {
        $prompts_data = json_decode(file_get_contents($prompts_file), true);
        // Try gpt5.2 key first, then gpt5.1, then gpt5, then gemini as fallback
        $system_prompt = $prompts_data['gpt5.2']['prompt'] 
            ?? $prompts_data['gpt5.1']['prompt'] 
            ?? $prompts_data['gpt5']['prompt'] 
            ?? $prompts_data['gemini']['prompt'] 
            ?? null;
    }
    // Fallback to legacy system_prompt.txt
    if (!$system_prompt) {
        $legacy_prompt = __DIR__ . '/../../ai/system_prompt.txt';
        if (file_exists($legacy_prompt)) {
            $system_prompt = file_get_contents($legacy_prompt);
        }
    }
    
    $json_schema_string = file_get_contents(__DIR__ . '/../../ai/schema.json');
    $json_schema = json_decode($json_schema_string, true);

    if (!$system_prompt || !$json_schema) {
        throw new Exception("Failed to load AI prompt or schema.");
    }

    // 5. PREPARE OPENAI API REQUEST
    // Convert visual_content (Gemini-style inlineData) to OpenAI image_url parts
    $userParts = [['type' => 'text', 'text' => $context_text]];
    if (is_array($visual_content)) {
        foreach ($visual_content as $vc) {
            if (isset($vc['inlineData'])) {
                $mime = $vc['inlineData']['mimeType'] ?? 'image/jpeg';
                $data = $vc['inlineData']['data'] ?? '';
                if (is_string($data) && $data !== '') {
                    $dataUrl = 'data:' . $mime . ';base64,' . $data;
                    $userParts[] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $dataUrl]
                    ];
                }
            } elseif (isset($vc['image_url'])) {
                // Already in OpenAI format
                $img = $vc['image_url'];
                if (is_array($img) && isset($img['url'])) {
                    $userParts[] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => $img['url']]
                    ];
                }
            }
        }
    }
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $userParts]
    ];

    // Determine function name from schema (fallbacks included)
    $function_name = 'draft_tree_quote';
    if (is_array($json_schema) && isset($json_schema['function']['name'])) {
        $function_name = $json_schema['function']['name'];
    } elseif (isset($json_schema['name'])) {
        $function_name = $json_schema['name'];
    }

    $openai_request = [
        'model' => 'gpt-5.2',
        'messages' => $messages,
        'max_completion_tokens' => 100000,
        'reasoning_effort' => 'xhigh',  // Maximum thinking for detailed tree analysis
    ];
    
    // Only add tools if schema is valid
    if ($json_schema && is_array($json_schema)) {
        $openai_request['tools'] = [$json_schema];
        $openai_request['tool_choice'] = ['type' => 'function', 'function' => ['name' => $function_name]];
    }

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
    // Resolve API key robustly (prefer process env over config var)
    $API_KEY = getenv('OPENAI_API_KEY') ?: '';
    if (!$API_KEY && isset($_ENV['OPENAI_API_KEY'])) {
        $envKey = $_ENV['OPENAI_API_KEY'];
        $API_KEY = is_array($envKey) ? (reset($envKey) ?: '') : $envKey;
    }
    if (!$API_KEY) {
        $API_KEY = is_array($OPENAI_API_KEY) ? (reset($OPENAI_API_KEY) ?: '') : ($OPENAI_API_KEY ?? '');
    }
    if (!$API_KEY) {
        throw new Exception("OpenAI API key not configured or empty");
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($openai_request),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $API_KEY
        ],
        CURLOPT_TIMEOUT => 300  // 5 minutes for large image processing
    ]);

    $start_time = microtime(true);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    $processing_time = (microtime(true) - $start_time) * 1000;

    if ($http_code !== 200) {
        throw new Exception("OpenAI GPT-5.2 API error. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
    }

    // 7. PARSE RESPONSE & CALCULATE COST
    $ai_result = json_decode($response, true);
    $ai_analysis_json = $ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? null;

    if (!$ai_analysis_json) {
        throw new Exception("Invalid GPT-5.2 response format or missing tool call. Full response: " . $response);
    }
    
    $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
    $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
    
    // 8. STORE RESULTS & TRACK COST
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'gpt-5.2',
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

$previous_history = [];
// Preserve prior runs so regenerated analyses do not wipe history
if (!empty($quote_data['ai_o4_mini_analysis'])) {
    $prev = json_decode($quote_data['ai_o4_mini_analysis'], true);
    if (is_array($prev)) {
        // Pull forward older history entries first
        if (isset($prev['history']) && is_array($prev['history'])) {
            $previous_history = array_values($prev['history']);
        }
        // Store the immediate previous snapshot (without nested history)
        $prev_snapshot = $prev;
        unset($prev_snapshot['history']);
        $previous_history[] = [
            'saved_at' => $prev_snapshot['timestamp'] ?? ($quote_data['updated_at'] ?? date('Y-m-d H:i:s')),
            'data' => $prev_snapshot
        ];
    }
}

$analysis_data_to_store = [
        'model' => 'gpt-5.2',
        'reasoning_effort' => 'xhigh',
        'analysis' => json_decode($ai_analysis_json, true),
        'cost' => $cost_data['total_cost'],
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
        'media_summary' => $aggregated_context['media_summary'],
        'history' => $previous_history
    ];

    // Save with connection recovery for long processing
    $json_to_store = json_encode($analysis_data_to_store, JSON_PRETTY_PRINT);
    error_log("GPT-5.2: Saving analysis for quote #{$quote_id}, JSON length: " . strlen($json_to_store));
    
    try {
        // Always get a fresh connection after long API call
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            throw new Exception("Failed to get database connection for saving");
        }
        
        $stmt = $pdo->prepare("UPDATE quotes SET ai_o4_mini_analysis = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$json_to_store, $quote_id]);
        
        if (!$result) {
            error_log("GPT-5.2: Save failed for quote #{$quote_id}: " . implode(', ', $stmt->errorInfo()));
            throw new Exception("Database update failed: " . implode(', ', $stmt->errorInfo()));
        }
        
        $rows_affected = $stmt->rowCount();
        error_log("GPT-5.2: Save successful for quote #{$quote_id}, rows affected: {$rows_affected}");
        
    } catch (PDOException $e) {
        error_log("GPT-5.2: PDO Exception for quote #{$quote_id}: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'server has gone away') !== false) {
            // Reconnect and retry
            $pdo = getDatabaseConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE quotes SET ai_o4_mini_analysis = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$json_to_store, $quote_id]);
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
        'model' => 'gpt-5.2',
        'reasoning_effort' => 'xhigh',
        'quote_id' => $quote_id,
        'analysis' => $analysis_data_to_store,
        'cost_tracking' => $cost_data
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'gpt-5.2',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null,
        'trace' => $e->getTraceAsString()
    ]);
}

// After success or failure, attempt to notify admin asynchronously when analysis succeeds
if (isset($analysis_data_to_store)) {
    try {
        require_once __DIR__ . '/admin-notification.php';
        // Fire-and-forget style; do not block response if it fails
        sendAdminNotification($quote_id);
    } catch (Throwable $notifyError) {
        error_log('Admin notification after GPT-5.2 analysis failed: ' . $notifyError->getMessage());
    }
    
    // Trigger media migration to static storage after successful analysis
    if (file_exists(__DIR__ . '/../utils/media_migrator.php')) {
        require_once __DIR__ . '/../utils/media_migrator.php';
        trigger_post_ai_migration($pdo, (int)$quote_id);
    }
}
?>
