<?php
// Manual Context Assessment Trigger for Testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../utils/context-assessor.php';

try {
    $quote_id = $_GET['quote_id'] ?? $_POST['quote_id'] ?? null;
    
    if (!$quote_id) {
        throw new Exception('quote_id parameter required');
    }
    
    $quote_id = (int)$quote_id;
    
    echo json_encode([
        'success' => true,
        'message' => "Starting context assessment for quote $quote_id...",
        'quote_id' => $quote_id
    ]);
    
    // Flush output to user immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Perform the assessment
    $assessment = ContextAssessor::assessSubmissionContext($quote_id);
    
    error_log("Manual context assessment for quote $quote_id completed: " . json_encode($assessment));
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 