<?php
// AI Pre-flight Check Script

ini_set('display_errors', 1);
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

    // 2. FETCH DATA
    $stmt = $pdo->prepare("SELECT q.*, c.name as customer_name FROM quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch();

    if (!$quote_data) {
        throw new Exception("Quote #{$quote_id} not found.");
    }

    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    // 3. PREPROCESS CONTEXT
    $preprocessor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $aggregated_context = $preprocessor->preprocessAllMedia();
    
    $context_text = $aggregated_context['context_text'];
    $visual_content = $aggregated_context['visual_content'];

    // 4. PREPARE OPENAI API REQUEST
    $system_prompt = "You are an expert arborist's assistant responsible for quality control. Your task is to determine if a customer has provided enough information to create a professional quote. Analyze the text and any images provided. Respond with a simple JSON object indicating if the submission is sufficient and what, if anything, is missing. Your response MUST be in the format: {\"sufficient\": boolean, \"missing_items\": [\"string\", ...]}";
    
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => array_merge([['type' => 'text', 'text' => $context_text]], $visual_content)]
    ];

    $openai_request = [
        'model' => 'o4-mini',
        'messages' => $messages,
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.1
    ];

    // 5. EXECUTE API CALL
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
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code !== 200) {
        throw new Exception("OpenAI o4-mini API error. HTTP Code: {$http_code}. Response: {$response}.");
    }

    // 6. PARSE & STORE RESPONSE
    $preflight_result = json_decode($response, true)['choices'][0]['message']['content'] ?? null;
    $preflight_data = json_decode($preflight_result, true);

    if (!$preflight_data || !isset($preflight_data['sufficient'])) {
        throw new Exception("Invalid pre-flight check response format.");
    }

    $stmt = $pdo->prepare("UPDATE quotes SET preflight_check_status = ? WHERE id = ?");
    $stmt->execute([json_encode($preflight_data), $quote_id]);
    
    // 7. SEND SUCCESS RESPONSE
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'preflight_check' => $preflight_data
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'quote_id' => $quote_id ?? null
    ]);
}
?>
