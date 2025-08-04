<?php
// Google Gemini 2.5 Pro Analysis Script

// Custom shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && (error_reporting() & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'model' => 'gemini-2.5-pro',
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
    echo json_encode(['success' => true, 'queued' => true, 'model' => 'gemini', 'quote_id' => $quote_id]);
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

    // 3. PREPROCESS MEDIA FOR GEMINI COMPATIBILITY
    $preprocessor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $processed_files = $preprocessor->preprocessForGemini();
    
    // Update media files with converted versions for processing
    $converted_media_files = [];
    foreach ($processed_files as $file) {
        $converted_media_files[] = [
            'file_path' => $file['file_path'],
            'filename' => $file['filename'],
            'mime_type' => $file['mime_type']
        ];
    }
    
    // Now process with converted files
    $preprocessor_final = new MediaPreprocessor($quote_id, $converted_media_files, $quote_data);
    $aggregated_context = $preprocessor_final->preprocessAllMedia();
    
    $context_text = $aggregated_context['context_text'];
    $visual_content = $aggregated_context['visual_content'];
    $transcriptions = $aggregated_context['transcriptions'];
    
    // Prepare media parts for Gemini API
    $media_parts = [];
    foreach ($visual_content as $content) {
        $media_parts[] = $content;
    }
    
    // Add transcriptions as text parts
    foreach ($transcriptions as $transcription) {
        $media_parts[] = ['text' => "🎤 " . $transcription['source'] . ": " . $transcription['text']];
    }

    // 4. LOAD AI PROMPTS & SCHEMA
    $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
    $json_schema_string = file_get_contents(__DIR__ . '/../../ai/schema.json');
    $json_schema = json_decode($json_schema_string, true);

    if (!$system_prompt || !$json_schema) {
        throw new Exception("Failed to load AI prompt or schema.");
    }

    // 5. PREPARE GEMINI API REQUEST
    $gemini_request = [
        'contents' => [
            'role' => 'user',
            'parts' => array_merge([['text' => $context_text]], $media_parts)
        ],
        'system_instruction' => ['role' => 'system', 'parts' => [['text' => $system_prompt]]],
        'tools' => [['function_declarations' => [$json_schema]]],
        'tool_config' => ['function_calling_config' => ['mode' => 'ANY']]
    ];

    // 6. EXECUTE API CALL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $GOOGLE_GEMINI_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($gemini_request),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 180
    ]);

    $start_time = microtime(true);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    $processing_time = (microtime(true) - $start_time) * 1000;

    if ($http_code !== 200) {
        throw new Exception("Google Gemini API error. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
    }

    // 7. PARSE RESPONSE & CALCULATE COST
    $gemini_result = json_decode($response, true);
    $ai_analysis_json = $gemini_result['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? null;

    if (!$ai_analysis_json) {
        throw new Exception("Invalid Gemini response format or missing tool call. Full response: " . $response);
    }
    
    $input_tokens = $gemini_result['usageMetadata']['promptTokenCount'] ?? 0;
    $output_tokens = $gemini_result['usageMetadata']['candidatesTokenCount'] ?? 0;
    
    // 8. STORE RESULTS & TRACK COST
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'gemini-2.5-pro',
        'provider' => 'google',
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
    ]);

    $analysis_data_to_store = [
        'model' => 'gemini-2.5-pro',
        'analysis' => $ai_analysis_json,
        'cost' => $cost_data['total_cost'],
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
        'media_summary' => $aggregated_context['media_summary']
    ];

    $stmt = $pdo->prepare("UPDATE quotes SET ai_gemini_analysis = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);

    // 9. SEND SUCCESS RESPONSE
    // Clean up any converted video files
    $preprocessor->cleanupConvertedFiles($processed_files);
    
    echo json_encode([
        'success' => true,
        'model' => 'gemini-2.5-pro',
        'quote_id' => $quote_id,
        'analysis' => $analysis_data_to_store,
        'cost_tracking' => $cost_data
    ]);

} catch (Throwable $e) {
    // Clean up any converted video files on error
    if (isset($preprocessor) && isset($processed_files)) {
        $preprocessor->cleanupConvertedFiles($processed_files);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'gemini-2.5-pro',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null,
        'trace' => $e->getTraceAsString()
    ]);
}
?>
