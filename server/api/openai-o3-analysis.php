<?php
// Custom shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && (error_reporting() & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'model' => 'o3',
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
    echo json_encode(['success' => true, 'queued' => true, 'model' => 'o3', 'quote_id' => $quote_id]);
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
    
    $context_text = $aggregated_context['context_text'] . "\n\nUse your advanced reasoning capabilities for a thorough, professional assessment.";
    $visual_content = $aggregated_context['visual_content'];

    // 4. LOAD AI PROMPTS & SCHEMA
    $system_prompt = 'You are a Board Master Certified Arborist (BMCA) specializing in comprehensive tree care sales and assessment for Carpe Tree\'em, a modern tree service company focused on preservation, longevity, and environmental stewardship.

CORE EXPERTISE & ANALYSIS:
- 20+ years experience in professional tree care and sales
- Species identification with confidence scoring (target 85%+ accuracy)
- Biomechanical assessment following ANSI A300 and ISA standards
- DBH measurement and canopy spread analysis using visual reference points
- Structural defect identification including codominant stems, decay, lean assessment
- Environmental context evaluation and site-specific risk factors

MEASUREMENT & ASSESSMENT REQUIREMENTS:
- Provide DUAL UNITS: Always report in both metric and imperial (height in m/ft, DBH in cm/inches)
- Estimate brush weight for removal jobs and visualize drop zones when applicable
- Assess tree health: excellent/good/fair/poor/dead with specific indicators
- Identify proximity to structures, power lines, and other risk factors
- Note access challenges and equipment requirements

PRICING & QUOTING STANDARDS (Ed Gilman Framework):
- Follow ANSI A300 Part 1 & Z133 safety standards - REFUSE all topping requests
- Quantify work scope: cut counts by diameter class (≤20mm, 20-50mm, >50mm)
- Calculate labour hours based on cut complexity and rigging requirements
- BC market rates: $80-120/hr for certified climbers (adjust by region/complexity)
- Include: gear/consumables, disposal costs, overhead/profit (typically 30%), risk adjusters
- Recommend appropriate pruning: cleaning, thinning, raising, reduction with specific cut locations
- For conifers within 20m of buildings: recommend sprinkler systems for wildfire protection
- Present as fixed-price quotes with detailed work scope attached

CUSTOMER INTERACTION:
- Prompt for additional images/videos if assessment requires more visual data
- Provide confidence ratings for each recommendation (aim for 85%+ where possible)
- Explain reasoning behind recommendations in accessible language
- Flag complex jobs requiring on-site assessment
- Maintain professional but user-friendly communication

OUTPUT REQUIREMENTS (Ed Gilman Specification Format):
Generate detailed quotes following Ed Gilman\'s framework:
1. PRUNING OBJECTIVE: Clear statement of goals and tree preservation priorities
2. TREE SUMMARY: Species, dimensions (height/DBH in dual units), scaffold mapping
3. WORK SCOPE & DOSE: Cut counts by diameter class and branch location/direction  
4. CUT LOCATION & TECHNIQUE: Specific collar/lateral cuts, tool requirements, rigging needs
5. TIMING: Optimal pruning windows considering species biology
6. SITE PROTECTION: Tarps, chip disposal, wood handling protocols
7. FOLLOW-UP: Long-term care recommendations and reassessment timeline
8. PRICING WORKSHEET: Labour hours → costs → overhead/profit → final quote

Present as professional specifications requiring minimal editing, with quantified cut lists and market-appropriate pricing for BC/Kootenay region. Include confidence ratings and equipment requirements for CRM integration.';
    
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
        'model' => 'o3', // Using the direct o3 model
        'messages' => $messages,
        'tools' => [$json_schema],
        'tool_choice' => ['type' => 'function', 'function' => ['name' => 'draft_tree_quote']],
        'max_completion_tokens' => 4000,
        
    ];

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
        CURLOPT_TIMEOUT => 180 // Generous timeout for a powerful model
    ]);

    $start_time = microtime(true);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    $processing_time = (microtime(true) - $start_time) * 1000;

    if ($http_code !== 200) {
        throw new Exception("OpenAI o3 API error. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
    }

    // 7. PARSE RESPONSE & CALCULATE COST
    $ai_result = json_decode($response, true);
    $ai_analysis_json = $ai_result['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? null;

    if (!$ai_analysis_json) {
        throw new Exception("Invalid o3 response format or missing tool call. Full response: " . $response);
    }
    
    $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
    $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
    
    // 8. STORE RESULTS & TRACK COST
    $cost_tracker = new CostTracker($pdo);
    $cost_data = $cost_tracker->trackUsage([
        'quote_id' => $quote_id,
        'model_name' => 'o3',
        'provider' => 'openai',
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
    ]);

    
        // Validate and clean up JSON response before storing
        $parsed_analysis = json_decode($ai_response, true);
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
        'model' => 'o3',
        'analysis' => json_decode($ai_analysis_json, true),
        'cost' => $cost_data['total_cost'],
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens,
        'processing_time_ms' => $processing_time,
        'media_summary' => $aggregated_context['media_summary']
    ];

    // Save with connection recovery for long-running o3 analysis
    try {
        $stmt = $pdo->prepare("UPDATE quotes SET ai_o3_analysis = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($analysis_data_to_store, JSON_PRETTY_PRINT), $quote_id]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'server has gone away') !== false) {
            // Reconnect and retry
            $pdo = getDatabaseConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE quotes SET ai_o3_analysis = ?, updated_at = NOW() WHERE id = ?");
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
        'model' => 'o3',
        'quote_id' => $quote_id,
        'analysis' => $analysis_data_to_store,
        'cost_tracking' => $cost_data
    ]);

} catch (Throwable $e) { // Catch both Exceptions and Errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'o3',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null,
        'trace' => $e->getTraceAsString()
    ]);
}
?>