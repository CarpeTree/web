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

    $models = [
        'o3' => $base_url . '/server/api/openai-o3-analysis.php',
        'o4-mini' => $base_url . '/server/api/openai-o4-mini-analysis.php',
        'gemini' => $base_url . '/server/api/google-gemini-analysis.php'
    ];

    $multi_curl = curl_multi_init();
    $requests = [];

    foreach ($models as $model => $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?quote_id=' . $quote_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5, // 5-second timeout to initiate the script
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ]);
        curl_multi_add_handle($multi_curl, $ch);
        $requests[$model] = $ch;
    }

    // Execute all requests asynchronously
    $running = null;
    do {
        curl_multi_exec($multi_curl, $running);
    } while ($running > 0);

    // Close handles
    foreach ($requests as $ch) {
        curl_multi_remove_handle($multi_curl, $ch);
    }
    curl_multi_close($multi_curl);

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
