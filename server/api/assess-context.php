<?php
// Manual Context Assessment Trigger for Testing
header('Content-Type: application/json');
// No permissive CORS; only same-origin callers should use this

require_once __DIR__ . '/../utils/context-assessor.php';

// Admin API key guard (optional; enforced if ADMIN_API_KEY is set)
function require_admin_key() {
    $expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
    if (!$expected) return;
    $provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['admin_key'] ?? $_POST['admin_key'] ?? null);
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}
require_admin_key();

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