<?php
phpinfo();
exit;

// Custom shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'model' => 'o3',
            'error' => 'A fatal error occurred: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'quote_id' => $_GET['quote_id'] ?? null
        ]);
    }
});

// Custom error handler to display fatal errors
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // OpenAI o3 Analysis - Advanced reasoning model via Direct OpenAI API
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

        // Prepare media content for o3 (high detail)
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
                        'detail' => 'high' // o3 can handle high detail
                    ]
                ];
            } elseif ($file_type === 'video') {
                // Extract frames for o3 analysis
                $size = round($media['file_size'] / (1024*1024), 1);
                $frames = extractVideoFrames($file_path, 5, 6); // Extract 6 frames
                if (!empty($frames)) {
                    $media_content = array_merge($media_content, $frames);
                } else {
                    $media_content[] = [
                        'type' => 'text',
                        'text' => "Video file: {$media['filename']} ({$size}MB). Frame extraction failed."
                    ];
                }
            }
        }

        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', $services);
        
        // Load the professional system prompt and schema
        $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
        $schema_json = file_get_contents(__DIR__ . '/../../ai/schema.json');
        $schema = json_decode($schema_json, true);
        
        require_once __DIR__ . '/../utils/media-preprocessor.php';
        $media_preprocessor = new MediaPreprocessor($pdo, $GEMINI_API_KEY);
        $aggregated_context = $media_preprocessor->aggregateContext($quote_id);

        // Use the aggregated context from preprocessor - o3 gets the FULL context
        $user_prompt = $aggregated_context['context_text'] . "\n\nUse your advanced reasoning capabilities to provide the most thorough professional assessment.";

        if (!empty($OPENAI_API_KEY) && !empty($aggregated_context['visual_content'])) {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => array_merge(
                        [['type' => 'text', 'text' => $user_prompt]], 
                        $aggregated_context['visual_content']
                    )
                ]
            ];

            $openai_request = [
                'model' => 'o3',
                'messages' => $messages,
                'tools' => [$schema],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'draft_tree_quote']],
                'max_completion_tokens' => 4000, // Higher limit for detailed analysis
                'temperature' => 0.1
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
                CURLOPT_TIMEOUT => 120 // Longer timeout for detailed reasoning
            ]);

            $start_time = microtime(true);
            $response = curl_exec($curl);
            $processing_time = (microtime(true) - $start_time) * 1000; // in milliseconds

            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($http_code !== 200) {
                throw new Exception("OpenAI o3 API error: HTTP $http_code");
            }

            $ai_result = json_decode($response, true);
            
            if (isset($ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
                $function_args = json_decode($ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);
                $ai_analysis = json_encode($function_args, JSON_PRETTY_PRINT);
            } elseif (isset($ai_result['choices'][0]['message']['content'])) {
                $ai_analysis = $ai_result['choices'][0]['message']['content'];
            } else {
                throw new Exception("Invalid o3 response format");
            }
            
            // Calculate cost (o3 pricing - advanced reasoning at budget-friendly rates)
            $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
            $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
            $cost = ($input_tokens * 0.000002) + ($output_tokens * 0.000008); // o3: $2/$8 per M tokens
            
        } else {
            $ai_analysis = "⚠️ o3 analysis unavailable. API key: " . (empty($OPENAI_API_KEY) ? "missing" : "set") . ", Media: " . count($aggregated_context['visual_content']) . " items";
            $cost = 0;
        }

        // Format the analysis
        $analysis_summary = "🧠 OpenAI o3 Analysis (Advanced Reasoning via Direct API)\n\n";
        $analysis_summary .= "📁 Media: " . implode(', ', $aggregated_context['media_summary']) . "\n";
        $analysis_summary .= "💰 Cost: $" . number_format($cost, 4) . "\n\n";
        $analysis_summary .= "🔍 Detailed Analysis:\n" . $ai_analysis;

        // Store results in database with model identifier
        $analysis_data = [
            'model' => 'o3',
            'analysis' => $ai_analysis,
            'cost' => $cost,
            'media_count' => count($media_files),
            'timestamp' => date('Y-m-d H:i:s'),
            'tokens_used' => ($input_tokens ?? 0) + ($output_tokens ?? 0)
        ];

        $stmt = $pdo->prepare("
            UPDATE quotes 
            SET ai_o3_analysis = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([json_encode($analysis_data), $quote_id]);

        // Track cost and performance
        require_once __DIR__ . '/../utils/cost-tracker.php';
        $cost_tracker = new CostTracker($pdo);
        
        $reasoning_effort = $_POST['reasoning_effort'] ?? 'medium';
        
        $cost_data = $cost_tracker->trackUsage([
            'quote_id' => $quote_id,
            'model_name' => 'o3',
            'provider' => 'openai',
            'input_tokens' => $input_tokens ?? 0,
            'output_tokens' => $output_tokens ?? 0,
            'processing_time_ms' => $processing_time,
            'reasoning_effort' => $reasoning_effort,
            'media_files_processed' => count($media_files),
            'transcriptions_generated' => 0, // Count actual transcriptions if available
            'tools_used' => ['advanced_reasoning', 'function_calling', 'vision', 'thinking_mode'],
            'analysis_quality_score' => 0.95 // Premium quality for reasoning model
        ]);

        echo json_encode([
            'success' => true,
                        'model' => 'o3',
            'quote_id' => $quote_id,
            'analysis' => $analysis_summary,
            'cost' => $cost,
            'cost_tracking' => $cost_data,
            'input_tokens' => $input_tokens ?? 0,
            'output_tokens' => $output_tokens ?? 0,
            'processing_time_ms' => $processing_time,
            'reasoning_effort' => $reasoning_effort,
            'media_count' => count($media_files)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
                        'model' => 'o3',
            'error' => $e->getMessage(),
            'quote_id' => $quote_id ?? null
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'o3',
        'error' => 'A fatal error occurred: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null
    ]);
}


require_once __DIR__ . '/../utils/utils.php';
?>