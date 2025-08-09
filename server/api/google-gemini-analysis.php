<?php
// Simplified Google Gemini 1.5 Pro Analysis Script

// Error and termination handling
register_shutdown_function(function () {
    $error = error_get_last();
    $quote_id = $_REQUEST['quote_id'] ?? 'N/A';
    if ($error !== null && (error_reporting() & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        error_log("Gemini Fatal Error for Quote #{$quote_id}: {$error['message']} in {$error['file']}:{$error['line']}");
        echo json_encode(['success' => false, 'model' => 'gemini-1.5-pro', 'error' => 'A fatal error occurred during Gemini analysis.']);
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
ignore_user_abort(true);
set_time_limit(600); // 10 minutes

try {
    // Handle command-line vs. web request
    if (php_sapi_name() === 'cli') {
        $quote_id = $argv[1] ?? null;
    } else {
        $quote_id = $_REQUEST['quote_id'] ?? null;
    }

    if (empty($quote_id)) {
        throw new Exception("Quote ID is required.");
    }
    
    // For web requests, send an immediate 'queued' response and continue in the background
    if (php_sapi_name() !== 'cli') {
        if (!headers_sent()) {
            header('X-Accel-Buffering: no');
            echo json_encode(['success' => true, 'queued' => true, 'model' => 'gemini', 'quote_id' => $quote_id]);
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../utils/media-preprocessor.php';
    require_once __DIR__ . '/../utils/cost-tracker.php';

    $pdo = getDatabaseConnection();

    // Fetch quote and media data
    $stmt = $pdo->prepare("SELECT q.*, c.name as customer_name, c.email as customer_email FROM quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote_data) throw new Exception("Quote #{$quote_id} not found.");

    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($media_files)) throw new Exception("No media files found for Gemini analysis.");

    // Preprocess media: convert videos, extract frames, transcribe audio
    $preprocessor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $gemini_ready_files = $preprocessor->preprocessForGemini();
    
    // Create a new preprocessor instance with the converted files to get final context
    $final_preprocessor = new MediaPreprocessor($quote_id, $gemini_ready_files, $quote_data);
    $aggregated_context = $final_preprocessor->preprocessAllMedia();
    
    // Prepare the request for Gemini API
    $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
    $json_schema = json_decode(file_get_contents(__DIR__ . '/../../ai/schema.json'), true);

    if (!$system_prompt || !$json_schema) throw new Exception("Failed to load AI prompt or schema.");

    $gemini_function = [
        'name' => $json_schema['function']['name'],
        'description' => $json_schema['function']['description'],
        'parameters' => $json_schema['function']['parameters']
    ];

    $user_parts = array_merge(
        [['text' => $aggregated_context['context_text']]],
        $aggregated_context['visual_content']
    );

    $gemini_request = [
        'contents' => [['role' => 'user', 'parts' => $user_parts]],
        'system_instruction' => ['parts' => [['text' => $system_prompt]]],
        'tools' => [['function_declarations' => [$gemini_function]]],
        'tool_config' => ['function_calling_config' => ['mode' => 'ANY']]
    ];

    // Call Gemini API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=' . $GOOGLE_GEMINI_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($gemini_request),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 300
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

    $gemini_result = json_decode($response, true);
    $ai_analysis_json = $gemini_result['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? null;

    if (!$ai_analysis_json) {
        error_log("Invalid Gemini Response for Quote #{$quote_id}: " . $response);
        throw new Exception("Invalid Gemini response format. See server logs for full details.");
    }
    
    // Track cost
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id, 'model_name' => 'gemini-1.5-pro-latest', 'provider' => 'google',
        'input_tokens' => $gemini_result['usageMetadata']['promptTokenCount'] ?? 0,
        'output_tokens' => $gemini_result['usageMetadata']['candidatesTokenCount'] ?? 0,
        'processing_time_ms' => $processing_time
    ]);

    // Store results
    $analysis_data_to_store = [
        'model' => 'gemini-1.5-pro-latest',
        'analysis' => $ai_analysis_json,
        'cost' => $cost_data['total_cost'],
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    $stmt = $pdo->prepare("UPDATE quotes SET ai_gemini_analysis = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);

    error_log("✅ Successfully completed Gemini analysis for Quote #{$quote_id}.");

} catch (Throwable $e) {
    http_response_code(500);
    $quote_id_for_log = $quote_id ?? 'N/A';
    error_log("Gemini Analysis Error for Quote #{$quote_id_for_log}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Update the database with an error message
    if (isset($pdo) && !empty($quote_id)) {
        $error_data = json_encode(['model' => 'gemini-1.5-pro', 'error' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')]);
        $pdo->prepare("UPDATE quotes SET ai_gemini_analysis = ? WHERE id = ?")->execute([$error_data, $quote_id]);
    }
}
