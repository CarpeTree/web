<?php
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
    // Google Gemini 2.5 Pro Analysis - Advanced multimodal reasoning
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

        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', $services);
        
        require_once __DIR__ . '/../utils/media-preprocessor.php';
        $media_preprocessor = new MediaPreprocessor($pdo, $GOOGLE_GEMINI_API_KEY);
        $aggregated_context = $media_preprocessor->aggregateContext($quote_id);

        $total_cost = 0;
        $processing_time = 0;
        
        if (!empty($GOOGLE_GEMINI_API_KEY)) {
            try {
                require_once __DIR__ . '/../utils/gemini-client.php';
                $gemini = new GeminiClient($GOOGLE_GEMINI_API_KEY);
                
                // Process the entire aggregated context with Gemini 2.5 Pro
                $start_time = microtime(true);
                $analysis = $gemini->analyzeAggregatedContextWithModel(
                    $aggregated_context,
                    'gemini-2.5-pro' // Specify the exact model
                );
                $processing_time = (microtime(true) - $start_time) * 1000; // in milliseconds
                $ai_analysis = $analysis['analysis'];
                $total_cost = $analysis['cost'] ?? 0;
                
            } catch (Exception $e) {
                error_log("Gemini 2.5 Pro analysis failed: " . $e->getMessage());
                $ai_analysis = "⚠️ Gemini 2.5 Pro analysis failed: " . $e->getMessage();
            }
        } else {
            $ai_analysis = "⚠️ Gemini 2.5 Pro analysis unavailable. API key not configured.";
        }

        // Format the analysis
        $analysis_summary = "🔮 Google Gemini 2.5 Pro Analysis (Advanced Multimodal)\n\n";
        $analysis_summary .= "📁 Media: " . implode(', ', array_column($aggregated_context['media_summary'], 'filename')) . "\n";
        $analysis_summary .= "💰 Cost: $" . number_format($total_cost, 4) . "\n\n";
        $analysis_summary .= "🔍 Multimodal Analysis:" . $ai_analysis;

        // Store results in database with model identifier
        $analysis_data = [
            'model' => 'gemini-2.5-pro',
            'analysis' => $ai_analysis,
            'cost' => $total_cost,
            'media_count' => count($media_files),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $stmt = $pdo->prepare("
            UPDATE quotes 
            SET ai_gemini_analysis = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([json_encode($analysis_data), $quote_id]);

        // Track cost and performance
        require_once __DIR__ . '/../utils/cost-tracker.php';
        $cost_tracker = new CostTracker($pdo);
        
        $cost_data = $cost_tracker->trackUsage([
            'quote_id' => $quote_id,
            'model_name' => 'gemini-2.5-pro',
            'provider' => 'google',
            'input_tokens' => 0, // Gemini API does not provide token counts
            'output_tokens' => 0,
            'processing_time_ms' => $processing_time,
            'reasoning_effort' => 'high',
            'media_files_processed' => count($media_files),
            'transcriptions_generated' => 0,
            'tools_used' => ['multimodal_reasoning', 'vision_analysis'],
            'analysis_quality_score' => 0.9
        ]);

        echo json_encode([
            'success' => true,
            'model' => 'gemini-2.5-pro',
            'quote_id' => $quote_id,
            'analysis' => $analysis_summary,
            'cost' => $total_cost,
            'cost_tracking' => $cost_data,
            'processing_time_ms' => $processing_time,
            'media_count' => count($media_files)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'model' => 'gemini-2.5-pro',
            'error' => $e->getMessage(),
            'quote_id' => $quote_id ?? null
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'gemini-2.5-pro',
        'error' => 'A fatal error occurred: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null
    ]);
}


require_once __DIR__ . '/../utils/utils.php';
?>