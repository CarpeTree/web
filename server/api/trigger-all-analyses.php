<?php
// Master AI Analysis Trigger Script
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $quote_id = $_POST['quote_id'] ?? null;
    if (!$quote_id) {
        throw new Exception("Quote ID is required.");
    }

    require_once __DIR__ . '/../config/database-simple.php';

    // Update quote status to show processing has started
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'multi_ai_processing' WHERE id = ?");
    $stmt->execute([$quote_id]);

    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

    // Launch each AI analysis script with PHP-CLI in the background
    $scripts = [
        'o3' => __DIR__ . '/openai-o3-analysis.php',
        'o4-mini' => __DIR__ . '/openai-o4-mini-analysis.php',
        'gemini' => __DIR__ . '/google-gemini-analysis.php'
    ];

    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0775, true);
    }

    if (function_exists('exec')) {
        foreach ($scripts as $model => $script_path) {
            $cmd = 'php ' . escapeshellarg($script_path) . ' ' . escapeshellarg($quote_id) .
                   ' >> ' . escapeshellarg($log_dir . '/ai_analysis.log') . ' 2>&1 &';
            exec($cmd);
        }
    } else {
        // Fallback to HTTP non-blocking trigger if exec is disabled
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $endpoints = [
            $base_url . '/server/api/openai-o3-analysis.php?quote_id=' . $quote_id,
            $base_url . '/server/api/openai-o4-mini-analysis.php?quote_id=' . $quote_id,
            $base_url . '/server/api/google-gemini-analysis.php?quote_id=' . $quote_id
        ];
        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_CUSTOMREQUEST => 'GET'
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    // The scripts are now running in the background.
    // We can add a check later to see if they are all complete.
    // For now, let's just confirm they were triggered.

    echo json_encode([
        'success' => true,
        'message' => 'All AI analyses have been triggered for quote #' . $quote_id,
        'quote_id' => $quote_id
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'quote_id' => $quote_id ?? null,
        'trace' => $e->getTraceAsString()
    ]);
}
?>
