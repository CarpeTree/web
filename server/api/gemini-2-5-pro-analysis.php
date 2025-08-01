<?php
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

ini_set('display_errors', 0); // Errors are caught by shutdown function
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    // 1. SETUP & CONFIG
    $quote_id = $_POST['quote_id'] ?? $_GET['quote_id'] ?? null;
    if (!$quote_id) {
        throw new Exception("Quote ID is required.");
    }
    
    require_once __DIR__ . '/../config/database-simple.php';
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../utils/media-preprocessor.php';
    require_once __DIR__ . '/../utils/cost-tracker.php';
    require_once __DIR__ . '/../utils/gemini-client.php';

    if (empty($GOOGLE_GEMINI_API_KEY)) {
        throw new Exception("Google Gemini API key is not configured.");
    }

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

    // 4. EXECUTE API CALL
    $gemini = new GeminiClient($GOOGLE_GEMINI_API_KEY);
    
    $start_time = microtime(true);
    // Use the dedicated method in the client to handle the new aggregated structure
    $analysis_result = $gemini->analyzeAggregatedContextWithModel($aggregated_context, 'gemini-1.5-pro-latest');
    $processing_time = (microtime(true) - $start_time) * 1000;

    $ai_analysis_json = $analysis_result['analysis'] ?? null;
    if (!$ai_analysis_json) {
        throw new Exception("Invalid Gemini response format or missing analysis. Full response: " . json_encode($analysis_result));
    }
    
    // 5. STORE RESULTS & TRACK COST
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'gemini-2.5-pro', // Name in our system
        'provider' => 'google',
        'input_tokens' => $analysis_result['input_tokens'] ?? 0,
        'output_tokens' => $analysis_result['output_tokens'] ?? 0,
        'processing_time_ms' => $processing_time,
    ]);

    $analysis_data_to_store = [
        'model' => 'gemini-2.5-pro',
        'analysis' => json_decode($ai_analysis_json, true), // store as array
        'cost' => $cost_data['total_cost'],
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'input_tokens' => $analysis_result['input_tokens'] ?? 0,
        'output_tokens' => $analysis_result['output_tokens'] ?? 0,
        'processing_time_ms' => $processing_time,
        'media_summary' => $aggregated_context['media_summary']
    ];

    $stmt = $pdo->prepare("UPDATE quotes SET ai_gemini_analysis = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);

    // 6. SEND SUCCESS RESPONSE
    echo json_encode([
        'success' => true,
        'model' => 'gemini-2.5-pro',
        'quote_id' => $quote_id,
        'analysis' => $analysis_data_to_store,
        'cost_tracking' => $cost_data
    ]);

} catch (Throwable $e) { // Catch both Exceptions and Errors
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